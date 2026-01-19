<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class vts extends Model
{
    use HasFactory;

    // A device belongs to an account.
    public function account()
    {
        return $this->belongsTo(VtsAccount::class, 'vts_account_id');
    }

    // An vts will have one billing record
    public function vtsBilling()
    {
        return $this->hasOne(VtsBilling::class, 'vts_id');
    }

    // This device will have many invoice items (billing for different periods)
    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class, 'vts_id');
    }

    // An vts will have one billing record
    public function billing()
    {
        return $this->hasOne(VtsBilling::class, 'vts_id');
    }

    // An vts can have many offers applied
    public function offers()
    {
        return $this->hasMany(VtsOffer::class, 'vts_id');
    }
}
