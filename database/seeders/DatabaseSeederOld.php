<?php

namespace Database\Seeders;

use Carbon\Carbon;
use App\Models\Vts;
use App\Models\Offer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\VtsOffer;
use App\Models\VtsAccount;
use App\Models\VtsBilling;
use App\Models\InvoiceItem;
use Faker\Factory as Faker;
use App\Models\CustomerLedger;
use App\Models\PaymentInvoice;
use App\Models\CustomerBilling;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();

        // Create some customers (vts_accounts)
        $company = [
            ['name' => 'Rahim Enterprise', 'customer_type' => 'retail', 'status' => 'active'],
            ['name' => 'Karim Motors', 'customer_type' => 'corporate', 'status' => 'active'],
            ['name' => 'Siddique Traders', 'customer_type' => 'retail', 'status' => 'active'],
            ['name' => 'Bismillah Logistics', 'customer_type' => 'corporate', 'status' => 'active'],
            ['name' => 'New Customer Test', 'customer_type' => 'retail', 'status' => 'active'],
        ];

        $accounts = new Collection();
        foreach ($company as $data) {
            $accounts->push(VtsAccount::create($data));
        }

        // Each account will have billing and 2-3 devices with billing
        $accounts->each(function ($account) use ($faker) {
            // Customer Billing config
            CustomerBilling::create([
                'vts_account_id' => $account->id,
                'bill_type' => $faker->randomElement(['prepaid', 'postpaid']),
                'billing_day' => $faker->numberBetween(1, 28),
                'current_balance' => 0,
                'last_invoice_id' => null,
                'last_pay_date' => null,
                'status' => 'active',
            ]);

            // Create 2–3 Vts devices per account
            $deviceCount = rand(2, 3);
            for ($i = 0; $i < $deviceCount; $i++) {
                $activation = Carbon::now()->subMonths(rand(1, 6));

                $vts = Vts::create([
                    'vts_account_id' => $account->id,
                    'activation_date' => $activation,
                    'imei' => '35' . rand(1000000000000, 9999999999999),
                ]);

                // Device Billing config
                VtsBilling::create([
                    'vts_id' => $vts->id,
                    'monthly_fee' => 350.00,
                    'actual_monthly_fee' => $faker->randomFloat(2, 300, 350),
                    'service_start_date' => $vts->activation_date,
                    'service_expiry_date' => null,
                    'next_billing_date' => $vts->activation_date->copy()->addMonths(1),
                    'current_balance' => 0,
                    'last_invoice_id' => null,
                    'last_pay_date' => null,
                    'status' => 'active',
                ]);
            }
        });

        // Create some offers
        $offersData = [
            [
                'title'       => 'New Device First Month Free',
                'valid_from'  => Carbon::now()->subDays(30),
                'valid_to'    => Carbon::now()->addMonths(3),
                'offer_type'  => 'free_month',
                'offer_value' => 0.00,
            ],
            [
                'title'       => 'Winter Special 10% Discount',
                'valid_from'  => Carbon::now()->subMonth(),
                'valid_to'    => Carbon::parse('2026-03-31'),
                'offer_type'  => 'percent',
                'offer_value' => 10.00,
            ],
            [
                'title'       => 'Referral Bonus 500 TK',
                'valid_from'  => Carbon::now(),
                'valid_to'    => null,
                'offer_type'  => 'fixed',
                'offer_value' => 500.00,
            ],
        ];

        $offers = collect();
        foreach ($offersData as $data) {
            $offers->push(Offer::create($data));
        }

        // Offer apply to random devices
        $allDevices = Vts::all();
        foreach ($offers as $offer) {
            $randomDevices = $allDevices->random(rand(2, 3));
            foreach ($randomDevices as $device) {
                VtsOffer::create([
                    'vts_id' => $device->id,
                    'offer_id' => $offer->id,
                    'applied_from' => now()->subDays(rand(0, 15)),
                    'applied_to' => $offer->valid_to ? $offer->valid_to : null,
                    'status' => 'active',
                ]);
            }
        }

        // Generate Invoices and Invoice Items
        $accounts->each(function ($account) {
            $vts = $account->vts;

            for ($i = 0; $i < rand(1, 3); $i++) {
                // $issueDate = Carbon::now()->subDays(rand(5, 90));
                $invoice = Invoice::create([
                    'vts_account_id' => $account->id,
                    'invoice_number' => "INV-" . rand(100000, 999999),
                    'issued_date' => null,
                    'due_date' => null,
                    'subtotal' => 0,
                    'discount_amount' => 0,
                    'total_amount' => 0,
                    'status' => 'draft',
                    'is_consolidated' => $vts->count() > 1,
                ]);

                $subtotal = 0;
                $totalDiscount = 0;

                $selectedDevices = $vts->random(min(3, $vts->count()));
                foreach ($selectedDevices as $device) {
                    $periodStart = $device->activation_date->copy()->addMonths($i);
                    $periodEnd = $periodStart->copy()->addDays(29);
                    // $quantity = rand(80, 100) / 100; // 0.8 to 1.0
                    $quantity = 1; // full month
                    $unitPrice = $device->vtsBilling->actual_monthly_fee;
                    $baseAmount = $unitPrice * $quantity;
                    
                    // Apply active offers starts
                    $discountAmount = 0;
                    $activeOffers = $device->active_offers;

                    foreach ($activeOffers as $vtsOffer) {
                        $offer = $vtsOffer->offer;
                        if ($offer->offer_type === 'free_month') {
                            $discountAmount = $baseAmount; // full free
                        } elseif ($offer->offer_type === 'percent') {
                            $discountAmount = $baseAmount * ($offer->offer_value / 100);
                        } elseif ($offer->offer_type === 'fixed') {
                            $discountAmount = $offer->offer_value;
                        }
                    }

                    $finalAmount = $baseAmount - $discountAmount;
                    $totalDiscount += $discountAmount;
                    // Apply active offers ends

                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'vts_id' => $device->id,
                        'period_start' => $periodStart,
                        'period_end' => $periodEnd,
                        'is_prorated' => $quantity != 1,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'discount_amount' => $discountAmount,
                        'amount' => $finalAmount,
                        'description' => "GPS Tracking - Device {$device->id}",
                    ]);

                    $subtotal += $baseAmount; // subtotal base amount
                }

                $invoice->update([
                    'subtotal' => $subtotal,
                    'discount_amount' => $totalDiscount,
                    'total_amount' => $subtotal - $totalDiscount,
                ]);

                // Simulate issue (70% of cases)
                if (rand(1, 10) <= 7) {
                    $invoice->issue(); // Here issued_date and due_date will be set.
                }
            }
        });

        // Payment + Allocation + Ledger
        $accounts->each(function ($account) use ($faker) {
            $invoices = $account->invoices;

            foreach ($invoices as $invoice) {
                if ($invoice->issued_date !== null) {
                    $remainingDue = $invoice->total_amount - $invoice->paid_amount;
                    if ($remainingDue > 0) {
                        $minPay = min(300, $remainingDue);
                        $maxPay = $remainingDue;
                        $payAmount = rand((int)$minPay, (int)$maxPay);
    
                        $payment = Payment::create([
                            'vts_account_id' => $account->id,
                            'amount' => $payAmount,
                            'payment_date' => Carbon::now()->subDays(rand(1, 30)),
                            'method' => $faker->randomElement(['bkash', 'nagad', 'bank']),
                            'reference' => strtoupper($faker->bothify('??######')),
                            'status' => 'success',
                        ]);
    
                        PaymentInvoice::create([
                            'payment_id' => $payment->id,
                            'invoice_id' => $invoice->id,
                            'allocated_amount' => $payAmount,
                        ]);
    
                        // Ledger entry
                        CustomerLedger::create([
                            'vts_account_id' => $account->id,
                            'transaction_date' => $payment->payment_date,
                            'type' => 'payment',
                            'reference_type' => Payment::class,
                            'reference_id' => $payment->id,
                            'debit' => 0,
                            'credit' => $payAmount,
                            'description' => 'Payment received',
                        ]);
    
                        $invoice->increment('paid_amount', $payAmount);
                        if ($invoice->paid_amount >= $invoice->total_amount) {
                            $invoice->update(['status' => 'paid']);
                        } elseif ($invoice->paid_amount > 0) {
                            $invoice->update(['status' => 'partially_paid']);
                        }
                    }
                }
            }
        });
    }
}
