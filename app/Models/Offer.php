<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    use HasFactory;

    protected $table = 'offers';

    protected $guarded = ['id'];

    // An offer will have many vts offers.
    public function vtsOffers()
    {
        return $this->hasMany(VtsOffer::class);
    }
}
