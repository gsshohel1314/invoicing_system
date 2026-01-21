<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentInvoiceItem extends Model
{
    use HasFactory;

    protected $table = 'payment_invoice_items';

    protected $guarded = ['id'];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function paymentInvoice()
    {
        return $this->belongsTo(PaymentInvoice::class);
    }

    public function invoiceItem()
    {
        return $this->belongsTo(InvoiceItem::class, 'invoice_item_id');
    }
}
