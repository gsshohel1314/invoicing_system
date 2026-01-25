<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerSummary extends Model
{
    protected $table = 'customer_summary';
    public $timestamps = false;
    protected $primaryKey = null;
    protected $guarded = [];

    protected $casts = [
        'last_invoice_date' => 'date',
        'last_pay_date'     => 'date',
        'current_balance'   => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function getFormattedBalanceAttribute()
    {
        return number_format($this->current_balance, 2) . ' ৳';
    }

    // last payment info formatted
    public function getLastPaymentInfoAttribute()
    {
        if (!$this->last_pay_date) {
            return 'No payments made yet.';
        }
        
        return 'last payment: ' . $this->last_pay_date->format('d M Y') . 
               ' (' . number_format($this->last_payment_amount ?? 0, 2) . ' ৳)';
    }
}
