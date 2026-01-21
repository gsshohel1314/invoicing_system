<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VtsAccount extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    // An account will have many devices.
    public function vts()
    {
        return $this->hasMany(Vts::class, 'vts_account_id');
    }

    // An account has one customer billing record.
    public function billing()
    {
        return $this->hasOne(CustomerBilling::class, 'vts_account_id');
    }

    // An account will have many invoices.
    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'vts_account_id');
    }

    // An account will have many payments.
    public function payments()
    {
        return $this->hasMany(Payment::class, 'vts_account_id');
    }

    // An account will have many customer ledger entries.
    public function ledgers()
    {
        return $this->hasMany(CustomerLedger::class, 'vts_account_id');
    }

    // current balance accessor (from ledger)
    public function getCurrentBalanceAttribute()
    {
        return $this->ledgers()->sum(DB::raw('credit - debit'));
    }
}
