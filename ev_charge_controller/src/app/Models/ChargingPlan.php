<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChargingPlan extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'generated_at' => 'immutable_datetime',
            'deadline_at' => 'immutable_datetime',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'minimum_energy_kwh' => 'float',
            'target_energy_kwh' => 'float',
            'planned_energy_kwh' => 'float',
            'estimated_cost' => 'float',
            'average_price_per_kwh' => 'float',
            'notes' => 'array',
        ];
    }

    public function setting()
    {
        return $this->belongsTo(ChargingSetting::class, 'charging_setting_id');
    }

    public function slots()
    {
        return $this->hasMany(ChargingPlanSlot::class)->orderBy('starts_at');
    }
}
