<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $table = 'invoice_items';

    protected $guarded = ['id'];

    protected $casts = [
        'period_start' => 'date',
        'period_end'   => 'date',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    // A invoice item belongs to an invoice.
    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    // A invoice belongs to an vts account.
    public function vtsAccount()
    {
        return $this->belongsTo(VtsAccount::class, 'vts_account_id');
    }

    // A invoice item belongs to a vts (device).
    public function vts()
    {
        return $this->belongsTo(Vts::class, 'vts_id');
    }

    // How much payment has been allocated for this item?
    public function allocations()
    {
        return $this->hasMany(PaymentInvoiceItem::class, 'invoice_item_id');
    }
}
