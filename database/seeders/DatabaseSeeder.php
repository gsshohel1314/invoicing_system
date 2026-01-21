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
    public function run(): void
    {
        $faker = Faker::create('bn_BD');

        // Create Vts Account
        $accountsData = [
            ['name' => 'রহিম এন্টারপ্রাইজ', 'customer_type' => 'retail'],
            ['name' => 'করিম মোটরস', 'customer_type' => 'corporate'],
            ['name' => 'সিদ্দিক ট্রেডার্স', 'customer_type' => 'retail'],
            // ['name' => 'বিসমিল্লাহ লজিস্টিকস', 'customer_type' => 'corporate'],
            // ['name' => 'নতুন কাস্টমার টেস্ট', 'customer_type' => 'retail'],
        ];

        $accounts = new Collection();
        foreach ($accountsData as $data) {
            $accounts->push(VtsAccount::create([
                'name'          => $data['name'],
                'customer_type' => $data['customer_type'],
                'status'        => 'active',
            ]));
        }

        // Create customer billing + Vts + Vts billing
        $accounts->each(function ($account) use ($faker) {
            // Customer Billing config
            CustomerBilling::create([
                'vts_account_id'         => $account->id,
                // 'bill_type'              => $faker->randomElement(['prepaid', 'postpaid']),
                'bill_type'              => 'prepaid',
                'invoice_generation_day' => 3,
                'billing_mode'           => 'calendar',
                'status'                 => 'active',
            ]);

            // Create 2 vts for every vts account
            $deviceCount = 2;
            for ($i = 0; $i < $deviceCount; $i++) {
                $activation = Carbon::now()->subMonths($faker->numberBetween(1, 12));

                // Create vts
                $vts = Vts::create([
                    'vts_account_id'  => $account->id,
                    'activation_date' => $activation,
                    'imei'            => '35' . $faker->unique()->numerify('#############'),
                ]);

                // Vts Billing config
                VtsBilling::create([
                    'vts_id'              => $vts->id,
                    'monthly_fee'         => 350.00,
                    'actual_monthly_fee'  => round($faker->randomFloat(2, 300, 350)),
                    'service_start_date'  => $vts->activation_date,
                    'service_expiry_date' => null,
                    'next_billing_date'   => $vts->activation_date->copy()->addMonths(1),
                    'status'              => 'active',
                ]);
            }
        });

        // Create offer
        // $offers = collect([
        //     Offer::create([
        //         'title'       => 'নতুন ডিভাইস প্রথম মাস ফ্রি',
        //         'description' => 'প্রথম মাসের বিল সম্পূর্ণ ফ্রি',
        //         'valid_from'  => Carbon::now()->subDays(30),
        //         'valid_to'    => Carbon::now()->addMonths(3),
        //         'offer_type'  => 'free_month',
        //         'offer_value' => 0.00,
        //     ]),
        //     Offer::create([
        //         'title'       => 'শীতকালীন অফার ১৫% ছাড়',
        //         'description' => 'ডিসেম্বর থেকে ফেব্রুয়ারি পর্যন্ত',
        //         'valid_from'  => Carbon::now()->subMonth(),
        //         'valid_to'    => Carbon::parse('2026-03-31'),
        //         'offer_type'  => 'percent',
        //         'offer_value' => 15.00,
        //     ]),
        //     Offer::create([
        //         'title'       => 'রেফারেল বোনাস',
        //         'description' => 'প্রতি সফল রেফারেলে ৫০০ টাকা',
        //         'valid_from'  => Carbon::now(),
        //         'valid_to'    => null,
        //         'offer_type'  => 'fixed',
        //         'offer_value' => 500.00,
        //     ]),
        // ]);

        // Apply offer in random device
        // $allDevices = Vts::all();
        // foreach ($offers as $offer) {
        //     $randomDevices = $allDevices->random(min(3, $allDevices->count()));
        //     foreach ($randomDevices as $device) {
        //         VtsOffer::create([
        //             'vts_id'       => $device->id,
        //             'offer_id'     => $offer->id,
        //             'applied_from' => Carbon::now()->subDays(rand(0, 60)),
        //             'applied_to'   => $offer->valid_to,
        //             'status'       => 'active',
        //         ]);
        //     }
        // }

        // Only 1 invoice per month for each customer.
        // $accounts->each(function ($account) use ($faker) {
        //     $devices = $account->vts;

        //     // Select a month (recent month)
        //     $issueDate = Carbon::now()->subMonths(rand(0, 3))->startOfMonth()->addDays(rand(1, 15));
        //     $billingMonth = $issueDate->format('Y-m');
        //     $periodStart = $issueDate->copy()->startOfMonth();
        //     $periodEnd = $issueDate->copy()->endOfMonth();

        //     // Check: Is there an invoice for this month?
        //     if ($account->invoices()->where('billing_month', $billingMonth)->exists()) {
        //         return; // Already have → Skip
        //     }

        //     $statusOptions = ['draft', 'unpaid', 'partially_paid', 'paid', 'overdue', 'cancelled'];
        //     $status = $faker->randomElement($statusOptions);

        //     $invoice = Invoice::create([
        //         'vts_account_id'       => $account->id,
        //         'invoice_number'       => 'INV-' . $issueDate->format('Ym') . '-' . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT),
        //         'billing_month'        => $billingMonth,
        //         'billing_period_start' => $periodStart,
        //         'billing_period_end'   => $periodEnd,
        //         'issued_date'          => $issueDate,
        //         'due_date'             => $issueDate->copy()->addDays(7),
        //         'subtotal'             => 0,
        //         'discount_amount'      => 0,
        //         'total_amount'         => 0,
        //         'paid_amount'          => 0,
        //         'status'               => $status,
        //         'is_consolidated'      => $devices->count() > 1,
        //         'is_advance_billed'    => $faker->boolean(30),
        //         'sent_at'              => $faker->boolean(70) ? $issueDate->copy()->addHours(rand(1, 48)) : null,
        //         'reminder_sent_count'  => rand(0, 4),
        //         'generated_by'         => $faker->randomElement(['cron', 'manual']),
        //     ]);

        //     $subtotal = 0;
        //     $discountTotal = 0;

        //     // $this->command->info('Device count: ' . $devices->count());
        //     foreach ($devices as $device) {
        //         $monthlyFee = $device->billing->actual_monthly_fee ?? 350.00;
        //         $quantity = 1.0000; // NO prorated — full month
        //         $baseAmount = $monthlyFee * $quantity;
        //         $discount = $faker->randomFloat(2, 0, $baseAmount * 0.25);

        //         $amount = $baseAmount - $discount;

        //         InvoiceItem::create([
        //             'invoice_id'      => $invoice->id,
        //             'vts_id'          => $device->id,
        //             'period_start'    => $periodStart,
        //             'period_end'      => $periodEnd,
        //             'is_prorated'     => false,
        //             'quantity'        => 1.0000,
        //             'unit_price'      => $monthlyFee,
        //             'discount_amount' => round($discount, 2),
        //             'amount'          => round($amount, 2),
        //         ]);

        //         $subtotal += $baseAmount;
        //         $discountTotal += $discount;
        //     }

        //     $invoice->update([
        //         'subtotal'      => round($subtotal, 2),
        //         'discount_amount' => round($discountTotal, 2),
        //         'total_amount'  => round($subtotal - $discountTotal, 2),
        //         // 'paid_amount'   => $status === 'paid' ? $invoice->total_amount : ($status === 'partially_paid' ? round($invoice->total_amount * 0.6, 2) : 0),
        //     ]);
        // });

        $this->command->info('Database seeding completed successfully!');
        $this->command->info('Total VtsAccounts: ' . VtsAccount::count());
        $this->command->info('Total Devices (VTS): ' . Vts::count());
        $this->command->info('Total Invoices: ' . Invoice::count());
        $this->command->info('Total Invoice Items: ' . InvoiceItem::count());
        $this->command->info('Total Payments: ' . Payment::count());
    }
}