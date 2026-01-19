<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VtsOffer extends Model
{
    use HasFactory;

    protected $table = 'vts_offers';

    public function vts()
    {
        return $this->belongsTo(Vts::class);
    }

    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }
}
