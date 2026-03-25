<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChargingPlanSlot extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'market_price_per_kwh' => 'float',
            'grid_price_per_kwh' => 'float',
            'import_price_per_kwh' => 'float',
            'effective_price_per_kwh' => 'float',
            'solar_surplus_kwh' => 'float',
            'allocated_energy_kwh' => 'float',
            'allocated_import_energy_kwh' => 'float',
            'allocated_solar_energy_kwh' => 'float',
            'recommended_power_kw' => 'float',
            'estimated_cost' => 'float',
            'execution_started_at' => 'immutable_datetime',
            'execution_finished_at' => 'immutable_datetime',
            'meter_started_kwh' => 'float',
            'meter_finished_kwh' => 'float',
            'executed_energy_kwh' => 'float',
        ];
    }

    public function plan()
    {
        return $this->belongsTo(ChargingPlan::class, 'charging_plan_id');
    }
}
