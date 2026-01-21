<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Vts;
use App\Models\Invoice;
use App\Models\VtsAccount;
use App\Models\InvoiceItem;
use App\Models\CustomerLedger;
use App\Models\CustomerBilling;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function generateInvoices()
    {
        $today = Carbon::today();

        // Get calendar mode customer
        $calendarAccounts = CustomerBilling::where('billing_mode', 'calendar')
            ->where('status', 'active')
            ->with('vtsAccount')
            ->get()
            ->map(fn($billing) => $billing->vtsAccount);

        // Calendar mode: Check invoice generation day per customer
        foreach ($calendarAccounts as $account) {
            $billingConfig = $account->billing;
            $genDay = $billingConfig->invoice_generation_day ?? 1; // Default 1st date
            $billingMonth = $today->format('Y-m');

            // If today >= generation day and no invoice for this month
            if ($today->day >= $genDay && !$this->hasInvoiceForMonth($account, $billingMonth)) {
                $this->generateCalendarInvoice($account, $billingMonth);
            }
        }
    }

    /**
     * Calendar mode: One consolidated invoice per month, prorated per device
     */
    private function generateCalendarInvoice(VtsAccount $account, string $billingMonth)
    {
        DB::transaction(function () use ($account, $billingMonth) {
            $monthStart = Carbon::parse($billingMonth . '-01');
            $monthEnd = $monthStart->copy()->endOfMonth();
            $daysInMonth = $monthStart->daysInMonth;

            // Make invoice 
            $invoice = Invoice::create([
                'vts_account_id'        => $account->id,
                'invoice_number'        => $this->generateInvoiceNumber(),
                'billing_month'         => $billingMonth,
                'billing_period_start'  => $monthStart,
                'billing_period_end'    => $monthEnd,
                'issued_date'           => today(),
                'due_date'              => today()->addDays(7), // Default after 7 days
                'subtotal'              => 0,
                'discount_amount'       => 0,
                'total_amount'          => 0,
                'paid_amount'           => 0,
                'status'                => 'draft',
                'is_consolidated'       => true,
                'is_advance_billed'     => true,
                'generated_by'          => 'cron'
            ]);

            $total = 0;

            $devices = $account->vts()
                ->where('service_status', 'active')
                ->where('activation_date', '<=', $monthEnd)
                ->get();

            foreach ($devices as $device) {
                $activation = Carbon::parse($device->activation_date);

                // effective active period in this month
                $effectiveStart = $activation->greaterThan($monthStart) ? $activation : $monthStart;
                $activeDays = $monthEnd->diffInDays($effectiveStart) + 1;

                $billing = $device->billing;
                $monthlyFee = $billing ? $billing->actual_monthly_fee : ($device->actual_monthly_fee ?? 350.00);
                $dailyRate = $monthlyFee / $daysInMonth;
                $amount = $dailyRate * $activeDays;

                InvoiceItem::create([
                    'invoice_id'        => $invoice->id,
                    'vts_id'            => $device->id,
                    'period_start'      => $effectiveStart,
                    'period_end'        => $monthEnd,
                    'is_prorated'       => $activeDays < $daysInMonth,
                    'quantity'          => round($activeDays / $daysInMonth, 4),
                    'unit_price'        => $monthlyFee,
                    'discount_amount'   => 0,
                    'amount'            => round($amount, 2),
                    'description'       => "GPS Tracking - {$monthStart->format('F Y')} (Prorated {$activeDays} days)",
                ]);

                $total += $amount;
            }

            $invoice->update([
                'subtotal'              => round($total, 2),
                'total_amount'          => round($total, 2),
                'status'                => 'unpaid',
                'sent_at'               => now(),
                'reminder_sent_count'   => 0,
            ]);

            // Ledger entry
            CustomerLedger::create([
                'vts_account_id'    => $account->id,
                'transaction_date'  => today(),
                'type'              => 'invoice',
                'debit'             => round($total, 2),
                'credit'            => 0,
                'reference_type'    => Invoice::class,
                'reference_id'      => $invoice->id,
                'description'       => "Consolidated invoice for {$billingMonth}",
            ]);
        });
    }

    /**
     * Check if there is an invoice for this month for any account.
     */
    private function hasInvoiceForMonth(VtsAccount $account, string $billingMonth)
    {
        return $account->invoices()->where('billing_month', $billingMonth)->exists();
    }

    private function generateInvoiceNumber()
    {
        return 'INV-' . now()->format('Ym') . '-' . str_pad(Invoice::max('id') + 1, 4, '0', STR_PAD_LEFT);
    }
}







// Get activation mode customer
// $activationAccounts = CustomerBilling::where('billing_mode', 'activation')
//     ->where('status', 'active')
//     ->with('account')
//     ->get()
//     ->map(function ($billing) {
//         $billing->account;
//     });

// Activation mode: Check next billing date of each device
// $devices = Vts::whereIn('vts_account_id', $activationAccounts->pluck('id'))
//     ->whereHas('billing', function ($query) use ($today) {
//         $query->where('status', 'active')
//             ->where('next_billing_date', '<=', $today);
//     })
//     ->get();

// $groupedDevices = $devices->groupBy('vts_account_id');

// foreach ($groupedDevices as $accountId => $accountDevices) {
//     $account = VtsAccount::find($accountId);
//     $this->generateActivationInvoice($account, $accountDevices);
// }