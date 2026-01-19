<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerBilling extends Model
{
    use HasFactory;

    protected $table = 'customer_billings';

    // The customer billing belongs to a VTS account.
    public function account()
    {
        return $this->belongsTo(VtsAccount::class, 'vts_account_id');
    }
}
