<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VtsBilling extends Model
{
    use HasFactory;

    protected $table = 'vts_billings';

    protected $guarded = ['id'];

    // The VTS billing belongs to a VTS device.
    public function vts()
    {
        return $this->belongsTo(Vts::class, 'vts_id');
    }
}
