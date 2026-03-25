<?php

use App\Models\ChargingPlan;
use App\Models\ChargingSetting;
use App\Models\PriceForecast;
use Illuminate\Support\Facades\Http;

test('execute charging plan command turns charger on for active slot', function () {
    config()->set('home_assistant.url', 'http://ha.local');
    config()->set('home_assistant.token', 'secret');
    config()->set('charging.defaults.charger_name', 'planner-test');
    config()->set('home_assistant.entities.charger_energy_total', 'sensor.c26634_energy_active_import_register');

    $settings = ChargingSetting::query()->create([
        'charger_name' => 'planner-test',
        'ha_charge_control_entity_id' => 'switch.c26634_charge_control',
        'ha_maximum_current_entity_id' => 'number.c26634_maximum_current',
        'battery_capacity_kwh' => 77,
        'current_soc_percent' => 55,
        'daily_minimum_soc_percent' => 55,
        'target_soc_percent' => 80,
        'daily_minimum_deadline' => '07:00',
        'charger_power_kw' => 11,
        'charger_min_current_amps' => 6,
        'charger_max_current_amps' => 16,
        'charger_efficiency' => 0.92,
        'grid_day_rate_per_kwh' => 0.1004,
        'grid_night_rate_per_kwh' => 0.057,
        'grid_weekend_rate_per_kwh' => 0.0645,
        'day_rate_starts_at' => '07:00',
        'night_rate_starts_at' => '22:00',
    ]);

    PriceForecast::query()->create([
        'starts_at' => now()->subMinutes(5),
        'ends_at' => now()->addMinutes(10),
        'market_price_per_kwh' => 0.03,
        'solar_surplus_kwh' => 0.5,
        'source' => 'test',
    ]);

    $plan = ChargingPlan::query()->create([
        'charging_setting_id' => $settings->id,
        'status' => 'planned',
        'generated_at' => now(),
        'deadline_at' => now()->addHour(),
        'starts_at' => now()->subMinutes(5),
        'ends_at' => now()->addMinutes(10),
        'minimum_energy_kwh' => 0,
        'target_energy_kwh' => 3,
        'planned_energy_kwh' => 2,
        'estimated_cost' => 0.5,
        'average_price_per_kwh' => 0.25,
    ]);

    $plan->slots()->create([
        'starts_at' => now()->subMinutes(5),
        'ends_at' => now()->addMinutes(10),
        'market_price_per_kwh' => 0.03,
        'grid_price_per_kwh' => 0.1004,
        'import_price_per_kwh' => 0.142,
        'effective_price_per_kwh' => 0.12,
        'solar_surplus_kwh' => 0.5,
        'allocated_energy_kwh' => 2.0,
        'allocated_import_energy_kwh' => 1.5,
        'allocated_solar_energy_kwh' => 0.5,
        'recommended_power_kw' => 11,
        'estimated_cost' => 0.21,
        'selection_bucket' => 'target',
        'rationale' => 'Test slot',
    ]);

    Http::fake([
        'http://ha.local/api/states/sensor.c26634_energy_active_import_register' => Http::response([
            'entity_id' => 'sensor.c26634_energy_active_import_register',
            'state' => '123.4',
        ], 200),
        'http://ha.local/api/services/number/set_value' => Http::response([], 200),
        'http://ha.local/api/services/switch/turn_on' => Http::response([], 200),
    ]);

    $this->artisan('app:execute-charging-plan')
        ->expectsOutputToContain('Charging at 16A')
        ->assertSuccessful();

    Http::assertSent(fn ($request) => $request->url() === 'http://ha.local/api/services/number/set_value'
        && $request['value'] === 16);
    Http::assertSent(fn ($request) => $request->url() === 'http://ha.local/api/services/switch/turn_on');
});

test('execute charging plan command finalizes ended slot using charger energy meter', function () {
    config()->set('home_assistant.url', 'http://ha.local');
    config()->set('home_assistant.token', 'secret');
    config()->set('home_assistant.entities.charger_energy_total', 'sensor.c26634_energy_active_import_register');

    $settings = ChargingSetting::query()->create([
        'charger_name' => 'planner-test',
        'ha_charge_control_entity_id' => 'switch.c26634_charge_control',
        'ha_maximum_current_entity_id' => 'number.c26634_maximum_current',
        'battery_capacity_kwh' => 40,
        'current_soc_percent' => 55,
        'daily_minimum_soc_percent' => 55,
        'target_soc_percent' => 80,
        'daily_minimum_deadline' => '07:00',
        'charger_power_kw' => 4,
        'charger_min_current_amps' => 6,
        'charger_max_current_amps' => 16,
        'charger_efficiency' => 0.92,
        'grid_day_rate_per_kwh' => 0.1004,
        'grid_night_rate_per_kwh' => 0.057,
        'grid_weekend_rate_per_kwh' => 0.0645,
        'day_rate_starts_at' => '07:00',
        'night_rate_starts_at' => '22:00',
    ]);

    $plan = ChargingPlan::query()->create([
        'charging_setting_id' => $settings->id,
        'status' => 'planned',
        'generated_at' => now()->subHour(),
        'deadline_at' => now()->addHour(),
        'starts_at' => now()->subMinutes(30),
        'ends_at' => now()->subMinutes(15),
        'minimum_energy_kwh' => 0,
        'target_energy_kwh' => 1,
        'planned_energy_kwh' => 1,
        'estimated_cost' => 0.2,
        'average_price_per_kwh' => 0.2,
    ]);

    $slot = $plan->slots()->create([
        'starts_at' => now()->subMinutes(30),
        'ends_at' => now()->subMinutes(15),
        'market_price_per_kwh' => 0.03,
        'grid_price_per_kwh' => 0.1004,
        'import_price_per_kwh' => 0.142,
        'effective_price_per_kwh' => 0.12,
        'solar_surplus_kwh' => 0,
        'allocated_energy_kwh' => 1.0,
        'allocated_import_energy_kwh' => 1.0,
        'allocated_solar_energy_kwh' => 0.0,
        'recommended_power_kw' => 4,
        'estimated_cost' => 0.12,
        'selection_bucket' => 'target',
        'rationale' => 'Test slot',
        'status' => 'active',
        'execution_started_at' => now()->subMinutes(30),
        'meter_started_kwh' => 123.4,
    ]);

    Http::fake([
        'http://ha.local/api/states/sensor.c26634_energy_active_import_register' => Http::response([
            'entity_id' => 'sensor.c26634_energy_active_import_register',
            'state' => '124.25',
        ], 200),
        'http://ha.local/api/services/switch/turn_off' => Http::response([], 200),
    ]);

    $this->artisan('app:execute-charging-plan')
        ->expectsOutputToContain('No scheduled charging slot is active right now.')
        ->assertSuccessful();

    expect($slot->fresh()->status)->toBe('completed');
    expect($slot->fresh()->executed_energy_kwh)->toBe(0.85);
});
