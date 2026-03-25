<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChargingSession extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'energy_kwh' => 'float',
            'average_price_per_kwh' => 'float',
            'estimated_peak_price_per_kwh' => 'float',
            'estimated_cost' => 'float',
            'estimated_peak_cost' => 'float',
            'savings_amount' => 'float',
            'meta' => 'array',
        ];
    }
}
