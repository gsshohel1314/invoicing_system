<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerLedger extends Model
{
    use HasFactory;

    protected $table = 'customer_ledgers';

    protected $guarded = ['id'];

    public function account()
    {
        return $this->belongsTo(VtsAccount::class, 'vts_account_id');
    }
}
