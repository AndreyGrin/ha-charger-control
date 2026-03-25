<?php

use App\Models\ChargingSetting;
use App\Models\PriceForecast;
use Illuminate\Support\Facades\Artisan;

test('dashboard renders charging overview content', function () {
    config()->set('charging.defaults.charger_name', 'test-charger');

    ChargingSetting::query()->create([
        'charger_name' => 'test-charger',
        'ha_charge_control_entity_id' => 'switch.c26634_charge_control',
        'ha_maximum_current_entity_id' => 'number.c26634_maximum_current',
        'battery_capacity_kwh' => 77,
        'current_soc_percent' => 41,
        'daily_minimum_soc_percent' => 55,
        'target_soc_percent' => 80,
        'daily_minimum_deadline' => '07:00',
        'charger_power_kw' => 11,
        'charger_min_current_amps' => 6,
        'charger_max_current_amps' => 16,
        'charger_efficiency' => 0.92,
        'grid_day_rate_per_kwh' => 0.08,
        'grid_night_rate_per_kwh' => 0.04,
        'grid_weekend_rate_per_kwh' => 0.05,
        'day_rate_starts_at' => '07:00',
        'night_rate_starts_at' => '22:00',
    ]);

    PriceForecast::query()->create([
        'starts_at' => now()->startOfHour(),
        'ends_at' => now()->startOfHour()->addHour(),
        'market_price_per_kwh' => 0.03,
        'solar_surplus_kwh' => 2.5,
        'source' => 'test',
    ]);

    $response = $this->get('/');

    $response
        ->assertOk()
        ->assertSee('Cheapest-charge planner')
        ->assertSee('test-charger')
        ->assertSee('Refresh Plan')
        ->assertSee('Execute Now')
        ->assertSee('Stop Charger');
});

test('dashboard plan action runs strategy command and redirects back', function () {
    Artisan::shouldReceive('call')
        ->once()
        ->with('app:evaluate-charging-strategy')
        ->andReturn(0);

    Artisan::shouldReceive('output')
        ->once()
        ->andReturn('Strategy refreshed');

    $response = $this->post('/actions/plan');

    $response
        ->assertRedirect('/')
        ->assertSessionHas('dashboard_status', 'Strategy refreshed');
});
