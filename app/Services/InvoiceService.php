<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Invoice;
use App\Models\VtsAccount;
use App\Models\InvoiceItem;
use App\Jobs\SendInvoiceEmail;
use App\Models\CustomerLedger;
use App\Models\Vts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceService
{
    public function generateInvoices()
    {
        $today = Carbon::today();
        $billingMonth = $today->format('Y-m');

        // Get calendar mode customer
        $calendarAccounts = VtsAccount::select('id', 'name', 'email', 'customer_type', 'status')
            ->where('customer_type', 'retail')
            ->where('status', 1)
            ->whereHas('billing', function ($q) {
                $q->where('billing_mode', 'calendar')
                ->where('bill_type', 'prepaid')
                ->where('status', 1);
            })
            ->with([
                'billing:id,vts_account_id,bill_type,billing_mode,invoice_generation_day,default_due_days,status'
            ])
            ->get();

        // Calendar mode: Check invoice generation day per customer
        foreach ($calendarAccounts as $account) {
            $genDay = $account->billing->invoice_generation_day ?? 1; // Default 1st date

            // If today >= generation day and no invoice for this month
            if ($today->day >= $genDay && !$this->hasInvoiceForMonth($account, $billingMonth)) {
                $this->generateCalendarInvoice($account, $billingMonth);
            }
        }
    }

    /**
     * Calendar mode: One consolidated invoice per month, prorated per device
     */
    private function generateCalendarInvoice(VtsAccount $account, string $billingMonth): void
    {
        try {
            DB::transaction(function () use ($account, $billingMonth) {
                $monthStart   = Carbon::parse($billingMonth . '-01')->startOfDay();
                $monthEnd     = $monthStart->copy()->endOfMonth();
                $daysInMonth  = $monthStart->daysInMonth;

                // Filter devices
                $devices = $account->vts()
                    ->select(['id', 'service_status'])
                    ->where('service_status', 'active')
                    ->whereHas('billing', function ($q) use ($monthStart, $monthEnd) {
                        $q->where('status', 1)
                        ->where('service_start_date', '<=', $monthEnd)
                        ->where(function ($q) use ($monthStart) {
                            $q->whereNull('service_expiry_date')
                                ->orWhere('service_expiry_date', '>=', $monthStart);
                        });
                    })
                    ->with([
                        'billing:id,vts_id,monthly_fee,actual_monthly_fee,device_install_date,service_start_date,service_expiry_date,status'
                    ])
                    ->get();

                if ($devices->isEmpty()) {
                    Log::info("Invoice skipped for account {$account->id} — no active devices");
                    return; // No billable devices; skip invoice
                }

                $total = 0;
                $invoiceItemsData = [];

                foreach ($devices as $device) {
                    $billing = $device->billing;
                    $serviceStart = Carbon::parse($billing->service_start_date);

                    // effective active period in this month
                    $effectiveStart = $serviceStart->greaterThan($monthStart) ? $serviceStart : $monthStart;
                    $activeDays = $monthEnd->diffInDays($effectiveStart) + 1;

                    $monthlyFee = $billing->actual_monthly_fee ?? $billing->monthly_fee ?? 350.00;

                    if ($monthlyFee <= 0) {
                        Log::info("Skipping device {$device->id} — invalid monthly fee");
                        continue;
                    }

                    // $amount = round(($monthlyFee * $activeDays) / $daysInMonth, 2);
                    $amount = round(($monthlyFee * $activeDays) / $daysInMonth, 0);

                    if ($amount <= 0) {
                        Log::info("Invoice item skipped for account {$account->id}, device {$device->id} — amount 0");
                        continue;
                    }

                    $invoiceItemsData[] = [
                        'vts_account_id' => $account->id,
                        'vts_id'         => $device->id,
                        'period_start'   => $effectiveStart,
                        'period_end'     => $monthEnd,
                        'is_prorated'    => $activeDays < $daysInMonth,
                        'quantity'       => round($activeDays / $daysInMonth, 4),
                        'unit_price'     => $monthlyFee,
                        'discount_amount'=> 0,
                        'amount'         => $amount,
                        'status'         => 'draft',
                        'description'    => "GPS Tracking - {$monthStart->format('F Y')} (Prorated {$activeDays} days)",
                    ];

                    $total += $amount;
                }

                if ($total <= 0) {
                    Log::info("Invoice skipped for account {$account->id} — total 0");
                    return; // Do not create zero-total invoice
                }

                // Create invoice
                $issuedDate = now();
                $dueDays = $account->billing ? $account->billing->default_due_days : 7;
                $dueDate = $issuedDate->copy()->addDays($dueDays);
                $invoice = Invoice::create([
                    'vts_account_id'       => $account->id,
                    'billing_month'        => $billingMonth,
                    'billing_period_start' => $monthStart,
                    'billing_period_end'   => $monthEnd,
                    'issued_date'          => $issuedDate,
                    'due_date'             => $dueDate,
                    'subtotal'             => $total,
                    'discount_amount'      => 0,
                    'total_amount'         => $total,
                    'paid_amount'          => 0,
                    'status'               => 'draft',
                    'is_consolidated'      => true,
                    'is_advance_billed'    => true,
                    'generated_by'         => 'cron',
                ]);

                $invoice->update([
                    'invoice_number' => $this->generateInvoiceNumber($invoice->id),
                ]);

                // Customer level balance update & ledger
                if ($account->billing) {
                    $account->billing->current_balance -= $total;
                    $account->billing->last_invoice_id = $invoice->id;
                    $account->billing->save();

                    // Ledger entry: debit for new due
                    CustomerLedger::create([
                        'vts_account_id'   => $account->id,
                        'transaction_date' => $issuedDate,
                        'type'             => 'invoice_due',
                        'debit'            => $total,
                        'credit'           => 0,
                        'reference_type'   => Invoice::class,
                        'reference_id'     => $invoice->id,
                        'description'      => "Invoice #{$invoice->invoice_number} consolidated due for {$billingMonth} ({$total} ৳)",
                    ]);
                }

                // Create invoice items
                $invoiceItemsData = array_map(function ($item) use ($invoice) {
                    $item['invoice_id'] = $invoice->id;
                    return $item;
                }, $invoiceItemsData);

                foreach ($invoiceItemsData as $itemData) {
                    $invoiceItem = InvoiceItem::create($itemData);
                    $vts = Vts::select(['id', 'service_status'])
                        ->with(['billing:id,vts_id,current_balance'])
                        ->find($invoiceItem->vts_id);

                    // Device (Vts) level balance update & ledger
                    if ($vts->billing) {
                        $vts->billing->current_balance -= $invoiceItem->amount;
                        $vts->billing->last_invoice_id = $invoiceItem->invoice_id;
                        $vts->billing->save();

                        CustomerLedger::create([
                            'vts_account_id'   => $invoiceItem->vts_account_id,
                            'vts_id'           => $vts->id,
                            'transaction_date' => $issuedDate,
                            'type'             => 'invoice_item_due',
                            'debit'            => $invoiceItem->amount,
                            'credit'           => 0,
                            'reference_type'   => InvoiceItem::class,
                            'reference_id'     => $invoiceItem->id,
                            'description'      => "Invoice #{$invoice->invoice_number} device #{$vts->id} prorated due for {$billingMonth} ({$invoiceItem->amount} ৳)",
                        ]);
                    }
                }

                // PDF generator
                if ($account->email) {
                    SendInvoiceEmail::dispatch($invoice)
                        ->onQueue('invoice-emails')
                        ->delay(now()->addSeconds(10)); // Short delay so that the invoice is fully committed
                }

                Log::info("Invoice {$invoice->invoice_number} created for account {$account->id} — total {$total}");
            });
        } catch (\Exception $e) {
            Log::error("Invoice generation failed for account {$account->id}: " . $e->getMessage());
        }
    }

    /**
     * Check if there is an invoice for this month for any account.
     */
    private function hasInvoiceForMonth(VtsAccount $account, string $billingMonth): bool
    {
        return $account->invoices()->where('billing_month', $billingMonth)->exists();
    }

    private function generateInvoiceNumber($invoiceId): string
    {
        return sprintf('INV-%s-%06d', now()->format('Ym'), $invoiceId);
    }
}