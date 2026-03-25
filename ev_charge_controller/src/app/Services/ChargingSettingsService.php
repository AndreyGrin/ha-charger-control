<?php

namespace App\Services;

use App\Models\ChargingSetting;

class ChargingSettingsService
{
    public function resolve(): ChargingSetting
    {
        $defaults = [
            ...$this->normalizedDefaults(),
            'ha_charge_control_entity_id' => (string) config('home_assistant.entities.charger_switch'),
            'ha_maximum_current_entity_id' => (string) config('home_assistant.entities.charger_max_current'),
        ];

        $settings = ChargingSetting::query()->latest()->first();

        if ($settings === null) {
            return ChargingSetting::query()->create([
                ...$defaults,
                'current_soc_percent' => 0,
            ]);
        }

        $settings->forceFill($defaults)->save();

        return $settings->fresh();
    }

    public function normalizedDefaults(): array
    {
        $defaults = config('charging.defaults', []);

        $defaults['grid_day_rate_per_kwh'] = $this->normalizeGridRate((float) $defaults['grid_day_rate_per_kwh']);
        $defaults['grid_night_rate_per_kwh'] = $this->normalizeGridRate((float) $defaults['grid_night_rate_per_kwh']);
        $defaults['grid_weekend_rate_per_kwh'] = $this->normalizeGridRate((float) $defaults['grid_weekend_rate_per_kwh']);

        return $defaults;
    }

    private function normalizeGridRate(float $value): float
    {
        $rate = config('charging.rates.prices_in_cents', true) ? ($value / 100) : $value;

        if (! config('charging.rates.prices_include_vat', false)) {
            $rate *= 1 + (float) config('charging.rates.vat_rate', 0.24);
        }

        return round($rate, 4);
    }
}
