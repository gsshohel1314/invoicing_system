<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $table = 'invoices';

    protected $guarded = ['id'];

    protected $casts = [
        'issued_date' => 'date',
        'due_date'    => 'date',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    // A invoice belongs to an vts account.
    public function vtsAccount()
    {
        return $this->belongsTo(VtsAccount::class, 'vts_account_id');
    }

    // An invoice will have many invoice items. (multiple devices)
    public function invoiceItems()
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
}
