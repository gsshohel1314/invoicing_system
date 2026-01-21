<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerBilling extends Model
{
    use HasFactory;

    protected $table = 'customer_billings';

    protected $guarded = ['id'];

    // The customer billing belongs to a VTS account.
    public function vtsAccount()
    {
        return $this->belongsTo(VtsAccount::class, 'vts_account_id');
    }
}
