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
use Illuminate\Support\Facades\Cache;

class InvoiceService
{
    private const CHUNK_SIZE = 100;
    private const LARGE_ACCOUNT_THRESHOLD = 500;
    private const INSERT_BATCH_SIZE = 500;

    // Generate invoices for all eligible accounts
    public function generateInvoices(): void
    {
        $today = Carbon::today();
        $billingMonth = $today->format('Y-m');

        $accounts = VtsAccount::query()
            ->select(['id', 'name', 'email', 'customer_type', 'status'])
            ->where('customer_type', 'retail')
            ->where('status', 1)
            ->whereHas('billing', function ($q) {
                $q->where('billing_mode', 'calendar')
                ->where('bill_type', 'prepaid')
                ->where('status', 1);
            })
            ->with('billing:id,vts_account_id,bill_type,billing_mode,invoice_generation_day,default_due_days,status')
            ->get();

        Log::info("Starting invoice generation batch", [
            'date' => $today->toDateString(),
            'billing_month' => $billingMonth,
            'accounts_count' => $accounts->count()
        ]);

        $stats = ['success' => 0, 'failed' => 0, 'skipped' => 0];

        foreach ($accounts as $account) {
            $genDay = $account->billing->invoice_generation_day ?? 1;

            // Not time yet or Already have invoice → skip
            if ($today->day < $genDay || $this->hasInvoiceForMonth($account, $billingMonth)) {
                $stats['skipped']++;
                continue;
            }

            try {
                $this->generateCalendarInvoice($account, $billingMonth);
                $stats['success']++;
            } catch (\Exception $e) {
                $stats['failed']++;
                Log::error("Invoice generation failed", [
                    'account_id' => $account->id,
                    'billing_month' => $billingMonth,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("Invoice generation batch completed", [
            'billing_month' => $billingMonth,
            'total_accounts' => $accounts->count(),
            'success' => $stats['success'],
            'failed' => $stats['failed'],
            'skipped' => $stats['skipped']
        ]);
    }

    // Generate calendar-based invoice for specific account
    public function generateCalendarInvoice(VtsAccount $account, string $billingMonth): void
    {
        $lock = Cache::lock("invoice:gen:{$account->id}:{$billingMonth}", 300);

        if (!$lock->get()) {
            Log::warning("Invoice already in progress", [
                'account_id' => $account->id,
                'billing_month' => $billingMonth,
            ]);
            return;
        }

        try {
            $startTime = microtime(true);
            $monthStart = Carbon::parse("{$billingMonth}-01")->startOfDay();
            $monthEnd = $monthStart->copy()->endOfMonth();
            $daysInMonth = $monthStart->daysInMonth;

            $deviceCount = $account->vts()
                ->where('service_status', 'active')
                ->whereHas('billing', function ($q) use ($monthStart, $monthEnd) {
                    $q->where('status', 1)
                    ->where('service_start_date', '<=', $monthEnd)
                    ->where(function ($q) use ($monthStart) {
                        $q->whereNull('service_expiry_date')
                            ->orWhere('service_expiry_date', '>=', $monthStart);
                    });
                })
                ->count();

            if ($deviceCount === 0) {
                Log::info("No devices found", [
                    'account_id' => $account->id,
                    'billing_month' => $billingMonth
                ]);
                return;
            }

            Log::info("Generating invoice", [
                'account_id' => $account->id,
                'billing_month' => $billingMonth,
                'devices' => $deviceCount,
                'mode' => $deviceCount > self::LARGE_ACCOUNT_THRESHOLD ? 'chunked' : 'batch'
            ]);

            DB::transaction(function () use ($account, $billingMonth, $monthStart, $monthEnd, $daysInMonth, $deviceCount, $startTime) {
                // Race condition check
                if ($this->hasInvoiceForMonth($account, $billingMonth)) {
                    Log::info("Invoice already exists (transaction check)", [
                        'account_id' => $account->id,
                        'billing_month' => $billingMonth
                    ]);
                    return;
                }

                // Calculate all items
                $data = $this->calculateAllItems($account, $monthStart, $monthEnd, $daysInMonth, $billingMonth, $deviceCount);

                if ($data['total'] <= 0) {
                    Log::info("Zero amount — invoice skipped", [
                        'account_id' => $account->id,
                        'billing_month' => $billingMonth
                    ]);
                    return;
                }

                // Create invoice
                $invoice = $this->createInvoice($account, $billingMonth, $monthStart, $monthEnd, $data['total']);
                $invoice->update(['invoice_number' => $this->generateInvoiceNumber($invoice->id)]);

                // Create items & update balances
                $this->createInvoiceItemsAndUpdateBalances($invoice, $data['items'], $billingMonth);
                $this->updateAccountBalance($account, $invoice, $data['total']);

                $this->queueEmail($account, $invoice);

                $time = round(microtime(true) - $startTime, 2);

                Log::info("Invoice created successfully", [
                    'invoice_number' => $invoice->invoice_number,
                    'account_id' => $account->id,
                    'billing_month' => $billingMonth,
                    'total' => $data['total'],
                    'items' => count($data['items']),
                    'time' => "{$time}s",
                    'memory' => $this->formatBytes(memory_get_peak_usage(true))
                ]);

                // Alert if slow
                if ($time > 60) {
                    Log::warning("Slow invoice generation", [
                        'account_id' => $account->id,
                        'time' => "{$time}s",
                        'devices' => $deviceCount
                    ]);
                }
            }, 3);

        } catch (\Exception $e) {
            Log::error("Invoice generation error", [
                'account_id' => $account->id,
                'billing_month' => $billingMonth,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } finally {
            $lock->release();
        }
    }

    // Calculate all invoice items (batch or chunked)
    private function calculateAllItems($account, $monthStart, $monthEnd, $daysInMonth, $billingMonth, $deviceCount): array
    {
        $total = 0;
        $items = [];

        $query = $account->vts()
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
            ->with('billing:id,vts_id,monthly_fee,actual_monthly_fee,service_start_date,service_expiry_date');

        // Use chunking for large accounts
        if ($deviceCount > self::LARGE_ACCOUNT_THRESHOLD) {
            Log::info("Using chunked processing", [
                'account_id' => $account->id,
                'total_chunks' => ceil($deviceCount / self::CHUNK_SIZE)
            ]);

            $chunkNum = 0;
            $query->chunk(self::CHUNK_SIZE, function ($chunk) use ($account, $monthStart, $monthEnd, $daysInMonth, &$total, &$items, &$chunkNum) {
                $chunkNum++;
                $chunkData = $this->calculateChunkItems($chunk, $account, $monthStart, $monthEnd, $daysInMonth);
                $total += $chunkData['total'];
                $items = array_merge($items, $chunkData['items']);

                // Per-chunk logging
                Log::info("Chunk {$chunkNum} processed", [
                    'account_id' => $account->id,
                    'devices' => $chunk->count(),
                    'items' => count($chunkData['items']),
                    'chunk_total' => $chunkData['total'],
                    'running_total' => $total
                ]);

                // GC every 10 chunks
                if ($chunkNum % 10 === 0) {
                    gc_collect_cycles();
                }
            });

            Log::info("Chunked processing complete", [
                'account_id' => $account->id,
                'total_chunks' => $chunkNum,
                'total_items' => count($items),
                'grand_total' => $total
            ]);
        } else {
            // Batch processing for small accounts
            $devices = $query->get();
            $chunkData = $this->calculateChunkItems($devices, $account, $monthStart, $monthEnd, $daysInMonth);
            $total = $chunkData['total'];
            $items = $chunkData['items'];
        }

        return ['total' => $total, 'items' => $items];
    }

    // Calculate items for device collection
    private function calculateChunkItems($devices, $account, $monthStart, $monthEnd, $daysInMonth): array
    {
        $total = 0;
        $items = [];

        foreach ($devices as $device) {
            $billing = $device->billing;

            if (!$billing) {
                Log::warning("Device missing billing info", [
                    'device_id' => $device->id,
                    'account_id' => $account->id
                ]);
                continue;
            }

            $monthlyFee = $billing->actual_monthly_fee ?? $billing->monthly_fee ?? 350;

            if ($monthlyFee <= 0) {
                Log::warning("Invalid monthly fee", [
                    'device_id' => $device->id,
                    'fee' => $monthlyFee
                ]);
                continue;
            }

            $serviceStart = Carbon::parse($billing->service_start_date);
            $serviceExpiry = $billing->service_expiry_date ? Carbon::parse($billing->service_expiry_date) : null;

            $effectiveStart = $serviceStart->gt($monthStart) ? $serviceStart : $monthStart;
            $effectiveEnd = $serviceExpiry && $serviceExpiry->lt($monthEnd) ? $serviceExpiry : $monthEnd;

            if ($effectiveEnd->lt($effectiveStart)) {
                Log::warning("Invalid date range", [
                    'device_id' => $device->id,
                    'start' => $effectiveStart->toDateString(),
                    'end' => $effectiveEnd->toDateString()
                ]);
                continue;
            }

            $activeDays = $effectiveEnd->diffInDays($effectiveStart) + 1;
            $amount     = round(($monthlyFee * $activeDays) / $daysInMonth, 0);

            if ($amount <= 0) continue;

            $items[] = [
                'vts_account_id'    => $account->id,
                'vts_id'            => $device->id,
                'period_start'      => $effectiveStart,
                'period_end'        => $effectiveEnd,
                'is_prorated'       => $activeDays < $daysInMonth,
                'quantity'          => round($activeDays / $daysInMonth, 4),
                'unit_price'        => $monthlyFee,
                'discount_amount'   => 0,
                'amount'            => $amount,
                'status'            => 'draft',
                'description'       => $activeDays < $daysInMonth
                    ? "GPS Tracking - {$monthStart->format('F Y')} (Prorated {$activeDays} days)"
                    : "GPS Tracking - {$monthStart->format('F Y')}",
            ];

            $total += $amount;
        }

        return ['items' => $items, 'total' => $total];
    }

    // Create invoice
    private function createInvoice($account, $billingMonth, $monthStart, $monthEnd, $total): Invoice
    {
        $issuedDate = now();
        $dueDays = $account->billing ? $account->billing->default_due_days : 7;
        $dueDate = $issuedDate->copy()->addDays($dueDays);

        return Invoice::create([
            'vts_account_id' => $account->id,
            'billing_month' => $billingMonth,
            'billing_period_start' => $monthStart,
            'billing_period_end' => $monthEnd,
            'issued_date' => $issuedDate,
            'due_date' => $dueDate,
            'subtotal' => $total,
            'discount_amount' => 0,
            'total_amount' => $total,
            'paid_amount' => 0,
            'status' => 'draft',
            'is_consolidated' => true,
            'is_advance_billed' => true,
            'generated_by' => 'cron',
        ]);
    }

    // Create items and update balances
    private function createInvoiceItemsAndUpdateBalances($invoice, $itemsData, $billingMonth): void
    {
        if (empty($itemsData)) return;

        // Prepare and insert items
        $prepared = array_map(fn($item) => array_merge($item, [
            'invoice_id' => $invoice->id,
            'created_at' => now(),
            'updated_at' => now()
        ]), $itemsData);

        foreach (array_chunk($prepared, self::INSERT_BATCH_SIZE) as $batch) {
            InvoiceItem::insert($batch);
        }

        // Get inserted items
        $items = InvoiceItem::where('invoice_id', $invoice->id)->get(['id', 'vts_id', 'amount', 'vts_account_id']);
        $billingMap = DB::table('vts_billings')->whereIn('vts_id', $items->pluck('vts_id'))->pluck('id', 'vts_id');

        // Bulk update device balances
        $this->bulkUpdateDeviceBalances($items, $billingMap, $invoice);

        // Create ledger entries
        $this->createDeviceLedgers($items, $invoice, $billingMonth);
    }

    // Bulk update device balances (CASE statement)
    private function bulkUpdateDeviceBalances($items, $billingMap, $invoice): void
    {
        $cases = [];
        $ids = [];

        foreach ($items as $item) {
            $billingId = $billingMap[$item->vts_id] ?? null;

            if (!$billingId) {
                Log::warning("Device has no billing record", [
                    'device_id' => $item->vts_id,
                    'invoice_item_id' => $item->id
                ]);
                continue;
            }

            // Sanitize amount to prevent SQL injection
            $amount = (float) $item->amount;
            $billingId = (int) $billingId;

            $cases[] = "WHEN id = {$billingId} THEN current_balance - {$amount}";
            $ids[] = $billingId;
        }

        if (empty($cases)) return;

        $caseSql = implode(' ', $cases);
        $idsStr = implode(',', $ids);
        $invoiceId = (int) $invoice->id;

        DB::statement("
            UPDATE vts_billings 
            SET current_balance = CASE {$caseSql} END,
                last_invoice_id = {$invoiceId},
                updated_at = NOW()
            WHERE id IN ({$idsStr})
        ");

        Log::debug("Bulk updated device balances", [
            'invoice_id' => $invoice->id,
            'devices_updated' => count($ids)
        ]);
    }

    // Create device ledger entries
    private function createDeviceLedgers($items, $invoice, $billingMonth): void
    {
        $entries = $items->map(fn($item) => [
            'vts_account_id' => $item->vts_account_id,
            'vts_id' => $item->vts_id,
            'transaction_date' => $invoice->issued_date,
            'type' => 'invoice_item_due',
            'debit' => $item->amount,
            'credit' => 0,
            'reference_type' => InvoiceItem::class,
            'reference_id' => $item->id,
            'description' => "Invoice #{$invoice->invoice_number} device #{$item->vts_id} due for {$billingMonth} ({$item->amount} ৳)",
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        foreach (array_chunk($entries, self::INSERT_BATCH_SIZE) as $batch) {
            CustomerLedger::insert($batch);
        }
    }

    // Customer level balance update & ledger
    private function updateAccountBalance($account, $invoice, $total): void
    {
        if (!$account->billing) {
            Log::warning("Account has no billing record", [
                'account_id' => $account->id
            ]);
            return;
        }

        // Sanitize total to prevent SQL injection
        $total = (float) $total;
        $invoiceId = (int) $invoice->id;

        DB::table('customer_billings')
            ->where('id', $account->billing->id)
            ->update([
                'current_balance' => DB::raw("current_balance - {$total}"),
                'last_invoice_id' => $invoiceId,
                'updated_at' => now(),
            ]);

        CustomerLedger::create([
            'vts_account_id' => $account->id,
            'transaction_date' => $invoice->issued_date,
            'type' => 'invoice_due',
            'debit' => $total,
            'credit' => 0,
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
            'description' => "Invoice #{$invoice->invoice_number} consolidated due for {$invoice->billing_month} ({$total} ৳)",
        ]);
    }

    // Queue invoice email
    private function queueEmail($account, $invoice): void
    {
        if (empty($account->email)) {
            Log::info("Account has no email — skipping notification", [
                'account_id' => $account->id,
                'invoice_id' => $invoice->id
            ]);
            return;
        }

        SendInvoiceEmail::dispatch($invoice)
            ->onQueue('invoice-emails')
            ->delay(now()->addSeconds(10));
    }

    // Check if invoice exists
    private function hasInvoiceForMonth($account, $billingMonth): bool
    {
        return Invoice::where([
            'vts_account_id' => $account->id,
            'billing_month' => $billingMonth
        ])->exists();
    }

    // Generate invoice number
    private function generateInvoiceNumber($id): string
    {
        return 'INV-' . date('Y') . '-' . str_pad($id, 5, '0', STR_PAD_LEFT);
    }

    // Format bytes
    private function formatBytes($bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $bytes >= 1024 && $i < 3; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}