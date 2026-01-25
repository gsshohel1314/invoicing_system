<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Invoice;
use App\Models\VtsAccount;
use App\Models\InvoiceItem;
use App\Jobs\SendInvoiceEmail;
use App\Models\CustomerLedger;
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
            ->with('billing:id,vts_account_id,bill_type,invoice_generation_day,billing_mode,status')
            ->where('customer_type', 'retail')
            ->where('status', 1)
            ->whereHas('billing', function ($q) {
                $q->where('billing_mode', 'calendar')
                ->where('bill_type', 'prepaid')
                ->where('status', 1);
            })
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

                // Filter devices that are active and activated before month end
                $devices = $account->vts
                    ->where('service_status', 'active')
                    ->where('activation_date', '<=', $monthEnd);

                if ($devices->isEmpty()) {
                    Log::info("Invoice skipped for account {$account->id} — no active devices");
                    return; // No billable devices; skip invoice
                }

                $total = 0;
                $invoiceItemsData = [];

                foreach ($devices as $device) {
                    $activation = Carbon::parse($device->activation_date);

                    // effective active period in this month
                    $effectiveStart = $activation->greaterThan($monthStart) ? $activation : $monthStart;
                    $activeDays = $monthEnd->diffInDays($effectiveStart) + 1;

                    $monthlyFee = data_get($device, 'billing.actual_monthly_fee', $device->actual_monthly_fee ?? 350.00); // with default amount
                    // $monthlyFee = data_get($device, 'billing.actual_monthly_fee'); // without default amount

                    if ($monthlyFee === null) {
                        Log::info("Skipping device {$device->id} — no unit_price set");
                        continue;
                    }

                    $amount = round(($monthlyFee * $activeDays) / $daysInMonth, 2);

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
                        'description'    => "GPS Tracking - {$monthStart->format('F Y')} (Prorated {$activeDays} days)",
                    ];

                    $total += $amount;
                }

                if ($total <= 0) {
                    Log::info("Invoice skipped for account {$account->id} — total 0");
                    return; // Do not create zero-total invoice
                }

                // Create invoice
                $invoice = Invoice::create([
                    'vts_account_id'       => $account->id,
                    'billing_month'        => $billingMonth,
                    'billing_period_start' => $monthStart,
                    'billing_period_end'   => $monthEnd,
                    'issued_date'          => now(),
                    'due_date'             => now()->addDays(config('billing.default_due_days', 7)),
                    'subtotal'             => $total,
                    'discount_amount'      => 0,
                    'total_amount'         => $total,
                    'paid_amount'          => 0,
                    'status'               => 'unpaid',
                    'is_consolidated'      => true,
                    'is_advance_billed'    => true,
                    'generated_by'         => 'cron',
                ]);

                $invoice->update([
                    'invoice_number' => $this->generateInvoiceNumber($invoice->id),
                ]);

                // Create invoice items
                foreach ($invoiceItemsData as $itemData) {
                    $itemData['invoice_id'] = $invoice->id;
                    InvoiceItem::create($itemData);
                }

                // Ledger entry
                CustomerLedger::create([
                    'vts_account_id'    => $account->id,
                    'transaction_date'  => now(),
                    'type'              => 'invoice',
                    'debit'             => round($total, 2),
                    'credit'            => 0,
                    'reference_type'    => Invoice::class,
                    'reference_id'      => $invoice->id,
                    'description'       => "Consolidated invoice for {$billingMonth}",
                ]);

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