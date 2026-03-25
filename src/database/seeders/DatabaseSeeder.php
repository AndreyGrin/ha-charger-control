<?php

namespace Database\Seeders;

use App\Models\ChargingSetting;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (ChargingSetting::query()->exists()) {
            return;
        }

        ChargingSetting::query()->create([
            'charger_name' => (string) config('charging.defaults.charger_name'),
            'ha_charge_control_entity_id' => (string) config('home_assistant.entities.charger_switch'),
            'ha_maximum_current_entity_id' => (string) config('home_assistant.entities.charger_max_current'),
            'battery_capacity_kwh' => (float) config('charging.defaults.battery_capacity_kwh'),
            'current_soc_percent' => 0,
            'daily_minimum_soc_percent' => (float) config('charging.defaults.daily_minimum_soc_percent'),
            'target_soc_percent' => (float) config('charging.defaults.target_soc_percent'),
            'daily_minimum_deadline' => (string) config('charging.defaults.daily_minimum_deadline'),
            'charger_power_kw' => (float) config('charging.defaults.charger_power_kw'),
            'charger_min_current_amps' => (int) config('charging.defaults.charger_min_current_amps'),
            'charger_max_current_amps' => (int) config('charging.defaults.charger_max_current_amps'),
            'charger_efficiency' => (float) config('charging.defaults.charger_efficiency'),
            'grid_day_rate_per_kwh' => (float) config('charging.defaults.grid_day_rate_per_kwh'),
            'grid_night_rate_per_kwh' => (float) config('charging.defaults.grid_night_rate_per_kwh'),
            'grid_weekend_rate_per_kwh' => (float) config('charging.defaults.grid_weekend_rate_per_kwh'),
            'day_rate_starts_at' => (string) config('charging.defaults.day_rate_starts_at'),
            'night_rate_starts_at' => (string) config('charging.defaults.night_rate_starts_at'),
        ]);
    }
}
