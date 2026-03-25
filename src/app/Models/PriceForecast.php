<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceForecast extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'market_price_per_kwh' => 'float',
            'solar_surplus_kwh' => 'float',
        ];
    }
}
