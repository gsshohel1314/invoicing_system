<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class invoice extends Model
{
    use HasFactory;

    protected $table = 'invoices';

    // A invoice belongs to an account.
    public function account()
    {
        return $this->belongsTo(VtsAccount::class, 'vts_account_id');
    }

    // An invoice will have many invoice items. (multiple devices)
    public function items()
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }

    // Which payments have been applied to this invoice?
    public function payments()
    {
        return $this->hasManyThrough(
            Payment::class,
            PaymentInvoice::class,
            'invoice_id',
            'id',
            'id',
            'payment_id'
        );
    }

    // How much payment has been allocated for this invoice?
    public function allocations()
    {
        return $this->hasMany(PaymentInvoice::class, 'invoice_id');
    }

    // Will be called when issuing invoice
    public function issue()
    {
        if ($this->status !== 'draft') {
            return; // Already issued
        }

        $this->issued_date = now();
        $this->due_date = now()->addDays(7); // issued_date + 7 days
        $this->status = 'unpaid'; // If there is no payment, unpaid
        $this->save();

        // Optional: Dispatch jobs to generate PDF, send email/SMS
        // Example:
        // GenerateInvoicePdfJob::dispatch($this);
        // SendInvoiceToCustomerJob::dispatch($this);
    }
}
