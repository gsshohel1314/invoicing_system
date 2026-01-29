<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\VtsAccount;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use App\Models\CustomerLedger;
use App\Models\PaymentInvoice;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function getCustomersByType($type)
    {
        $customers = VtsAccount::where('customer_type', $type)
            ->where('status', 1)
            ->select('id', 'name')
            ->get();

        return $customers;
    }

    public function getUnpaidInvoices($accountId)
    {
        $invoices = Invoice::where('vts_account_id', $accountId)
            ->whereIn('status', ['unpaid', 'partially_paid'])
            ->select('id', 'invoice_number', 'due_amount')
            ->get();
        
        return $invoices;
    }

    public function getInvoiceItems($invoiceId)
    {
        $invoiceItems = InvoiceItem::where('invoice_id', $invoiceId)
            ->whereIn('status', ['unpaid', 'partially_paid'])
            // ->orderBy('due_amount', 'desc')
            // ->orderBy('id', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'id'           => $item->id,
                    'description'  => $item->description ?? 'N/A',
                    'period_start' => $item->period_start->format('d M Y') ?? 'N/A',
                    'period_end'   => $item->period_end->format('d M Y') ?? 'N/A',
                    'unit_price'   => $item->unit_price,
                    'amount'       => $item->amount,
                    'paid_amount'  => $item->paid_amount,
                    'due_amount'   => $item->due_amount,
                ];
            });

        return $invoiceItems;
    }

    public function create()
    {
        $accounts = VtsAccount::where('status', 1)->get(['id', 'name']);
        $invoices = Invoice::whereIn('status', ['unpaid', 'partially_paid'])->get(['id', 'invoice_number', 'total_amount', 'paid_amount']);

        return view('payments.create', compact('accounts', 'invoices'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'vts_account_id'   => 'required|exists:vts_accounts,id',
            'amount'           => 'required|numeric|min:0.01',
            'payment_date'     => 'required|date',
            'method'           => 'required|in:cash,bkash,nagad,bank',
            'reference'        => 'nullable|string',
            'invoice_id'       => 'required|exists:invoices,id',
            'allocated_amount' => 'nullable|array',
            'allocated_amount.*' => 'numeric|min:0',
        ]);

        DB::transaction(function () use ($validated, $request) {
            // Payment record
            $payment = Payment::create([
                'vts_account_id' => $validated['vts_account_id'],
                'invoice_id'     => $validated['invoice_id'],
                'amount'         => $validated['amount'],
                'payment_date'   => $validated['payment_date'],
                'method'         => $validated['method'],
                'reference'      => $validated['reference'],
                'status'         => 'success',
            ]);

            // Ledger entry (credit)
            CustomerLedger::create([
                'vts_account_id'   => $validated['vts_account_id'],
                'transaction_date' => $validated['payment_date'],
                'type'             => 'payment',
                'reference_type'   => Payment::class,
                'reference_id'     => $payment->id,
                'debit'            => 0,
                'credit'           => $validated['amount'],
                'description'      => "Payment via {$validated['method']} - Ref: {$validated['reference']}",
            ]);

            // Invoice level allocation
            $invoice = Invoice::findOrFail($validated['invoice_id']);

            PaymentInvoice::create([
                'payment_id'       => $payment->id,
                'invoice_id'       => $invoice->id,
                'vts_account_id'   => $validated['vts_account_id'],
                'allocated_amount' => $validated['amount'],
                'total_amount'     => $invoice->total_amount,
            ]);

            // Invoice paid_amount update + status recalculate
            $invoice->paid_amount += $validated['amount'];
            $invoice->status = ($invoice->total_amount - $invoice->paid_amount <= 0) ? 'paid' : 'partially_paid';
            $invoice->save();

            // Invoice item-wise allocation & status update
            $allocated = $request->input('allocated_amount', []);
            foreach ($allocated as $itemId => $allocatedAmount) {
                if ($allocatedAmount > 0 && is_numeric($itemId)) {
                    $item = InvoiceItem::find($itemId);

                    if ($item && $item->invoice_id == $invoice->id) {
                        $item->paid_amount += $allocatedAmount;
                        $item->status = ($item->amount - $item->paid_amount <= 0) ? 'paid' : 'partially_paid';
                        $item->save();
                    }
                }
            }

            // যদি overpayment থাকে (যেমন 300 দিয়ে 280 due) — balance-এ যোগ করো
            // ... (তোমার balance logic)
            // $totalAllocated = array_sum($allocated);
            // $overPayment = $validated['amount'] - $totalAllocated;
            // if ($overPayment > 0) {
            //     $account = $invoice->vtsAccount;
            //     $account->billing->current_balance += $overPayment;
            //     $account->billing->save();

            //     CustomerLedger::create([
            //         'vts_account_id'   => $account->id,
            //         'transaction_date' => now(),
            //         'type'             => 'advance_payment',
            //         'debit'            => 0,
            //         'credit'           => $overPayment,
            //         'description'      => "Advance from payment #{$payment->id}",
            //     ]);
            // }
        });
    }
}
