<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VtsBilling extends Model
{
    use HasFactory;

    protected $table = 'vts_billings';

    protected $guarded = ['id'];

    protected $casts = [
        'device_install_date' => 'date',
        'service_start_date'  => 'date',
        'service_expiry_date' => 'date',
        'next_billing_date'   => 'date',
        'last_pay_date'       => 'date',
    ];

    // The VTS billing belongs to a VTS device.
    public function vts()
    {
        return $this->belongsTo(Vts::class, 'vts_id');
    }
}
