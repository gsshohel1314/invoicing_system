<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $table = 'payments';

    protected $guarded = ['id'];

    // A payment belongs to an account.
    public function account()
    {
        return $this->belongsTo(VtsAccount::class, 'vts_account_id');
    }

    // This payment has been allocated to which invoice?
    public function invoices()
    {
        return $this->hasManyThrough(
            Invoice::class,
            PaymentInvoice::class,
            'payment_id',
            'id',
            'id',
            'invoice_id'
        );
    }

    // Allocation details
    public function allocations()
    {
        return $this->hasMany(PaymentInvoice::class, 'payment_id');
    }
}
