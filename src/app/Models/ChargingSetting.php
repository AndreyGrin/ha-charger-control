<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChargingSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'battery_capacity_kwh' => 'float',
            'current_soc_percent' => 'float',
            'daily_minimum_soc_percent' => 'float',
            'target_soc_percent' => 'float',
            'charger_power_kw' => 'float',
            'charger_min_current_amps' => 'integer',
            'charger_max_current_amps' => 'integer',
            'charger_efficiency' => 'float',
            'grid_day_rate_per_kwh' => 'float',
            'grid_night_rate_per_kwh' => 'float',
            'grid_weekend_rate_per_kwh' => 'float',
        ];
    }

    public function chargingPlans()
    {
        return $this->hasMany(ChargingPlan::class);
    }
}
