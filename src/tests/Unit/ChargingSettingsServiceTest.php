<?php

use App\Services\ChargingSettingsService;

test('charging settings service normalizes grid tariffs from cents without vat', function () {
    config()->set('charging.defaults.grid_day_rate_per_kwh', 8.1);
    config()->set('charging.defaults.grid_night_rate_per_kwh', 4.6);
    config()->set('charging.defaults.grid_weekend_rate_per_kwh', 5.2);
    config()->set('charging.rates.prices_in_cents', true);
    config()->set('charging.rates.prices_include_vat', false);
    config()->set('charging.rates.vat_rate', 0.24);

    $defaults = app(ChargingSettingsService::class)->normalizedDefaults();

    expect($defaults['grid_day_rate_per_kwh'])->toBe(0.1004);
    expect($defaults['grid_night_rate_per_kwh'])->toBe(0.057);
    expect($defaults['grid_weekend_rate_per_kwh'])->toBe(0.0645);
});
