<?php

use App\Models\ChargingSetting;
use App\Services\HomeAssistantService;
use Illuminate\Support\Facades\Http;

test('home assistant service clamps current and starts charging', function () {
    config()->set('home_assistant.url', 'http://ha.local');
    config()->set('home_assistant.token', 'secret');

    Http::fake([
        'http://ha.local/api/services/number/set_value' => Http::response([], 200),
        'http://ha.local/api/services/switch/turn_on' => Http::response([], 200),
    ]);

    $settings = ChargingSetting::query()->create([
        'charger_name' => 'planner-test',
        'ha_charge_control_entity_id' => 'switch.c26634_charge_control',
        'ha_maximum_current_entity_id' => 'number.c26634_maximum_current',
        'battery_capacity_kwh' => 77,
        'current_soc_percent' => 30,
        'daily_minimum_soc_percent' => 50,
        'target_soc_percent' => 70,
        'daily_minimum_deadline' => '07:00',
        'charger_power_kw' => 11,
        'charger_min_current_amps' => 6,
        'charger_max_current_amps' => 16,
        'charger_efficiency' => 0.92,
        'grid_day_rate_per_kwh' => 0.09,
        'grid_night_rate_per_kwh' => 0.03,
        'grid_weekend_rate_per_kwh' => 0.04,
        'day_rate_starts_at' => '07:00',
        'night_rate_starts_at' => '22:00',
    ]);

    app(HomeAssistantService::class)->applyChargingCommand($settings, 20, true);

    Http::assertSent(fn ($request) => $request->url() === 'http://ha.local/api/services/number/set_value'
        && $request['entity_id'] === 'number.c26634_maximum_current'
        && $request['value'] === 16
    );

    Http::assertSent(fn ($request) => $request->url() === 'http://ha.local/api/services/switch/turn_on'
        && $request['entity_id'] === 'switch.c26634_charge_control'
    );
});

test('home assistant service returns configured entity states', function () {
    config()->set('home_assistant.url', 'http://ha.local');
    config()->set('home_assistant.token', 'secret');
    config()->set('home_assistant.entities', [
        'vehicle_soc' => 'sensor.car_soc',
        'nordpool' => 'sensor.nordpool_ee_eur_3_10_024',
        'solar_forecast_east' => 'sensor.energy_production_today',
        'solar_forecast_west' => 'sensor.energy_production_today_2',
        'solar_current_hour_east' => 'sensor.energy_current_hour',
        'solar_current_hour_west' => 'sensor.energy_current_hour_2',
        'solar_next_hour_east' => 'sensor.energy_next_hour',
        'solar_next_hour_west' => 'sensor.energy_next_hour_2',
    ]);

    Http::fake([
        'http://ha.local/api/states' => Http::response([
            [
                'entity_id' => 'sensor.car_soc',
                'state' => '61',
                'last_updated' => '2026-03-24T16:00:00+00:00',
            ],
            [
                'entity_id' => 'sensor.nordpool_ee_eur_3_10_024',
                'state' => '0.054',
                'last_updated' => '2026-03-24T16:00:00+00:00',
            ],
            [
                'entity_id' => 'sensor.energy_production_today',
                'state' => '12.4',
                'last_updated' => '2026-03-24T16:00:00+00:00',
            ],
            [
                'entity_id' => 'sensor.energy_production_today_2',
                'state' => '10.8',
                'last_updated' => '2026-03-24T16:00:00+00:00',
            ],
            [
                'entity_id' => 'sensor.energy_current_hour',
                'state' => '1.2',
                'last_updated' => '2026-03-24T16:00:00+00:00',
            ],
            [
                'entity_id' => 'sensor.energy_current_hour_2',
                'state' => '0.9',
                'last_updated' => '2026-03-24T16:00:00+00:00',
            ],
            [
                'entity_id' => 'sensor.energy_next_hour',
                'state' => '0.7',
                'last_updated' => '2026-03-24T16:00:00+00:00',
            ],
            [
                'entity_id' => 'sensor.energy_next_hour_2',
                'state' => '0.6',
                'last_updated' => '2026-03-24T16:00:00+00:00',
            ],
        ], 200),
    ]);

    $states = app(HomeAssistantService::class)->configuredStates();

    expect($states->get('vehicle_soc')['state'])->toBe('61');
    expect($states->get('nordpool')['entity_id'])->toBe('sensor.nordpool_ee_eur_3_10_024');
    expect(app(HomeAssistantService::class)->combinedSolarForecastState())->toBe(23.2);
    expect(app(HomeAssistantService::class)->combinedSolarCurrentHourState())->toBe(2.1);
    expect(app(HomeAssistantService::class)->combinedSolarNextHourState())->toBe(1.3);
});
