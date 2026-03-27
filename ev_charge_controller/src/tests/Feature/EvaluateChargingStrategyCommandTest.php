<?php

use App\Models\ChargingPlan;
use App\Models\ChargingSetting;
use App\Models\PriceForecast;
use Illuminate\Support\Facades\Http;

test('strategy command persists a plan using cheapest and solar-assisted windows', function () {
    ChargingSetting::query()->create([
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

    collect([
        [0, 0.08, 0.0],
        [1, 0.02, 0.0],
        [2, 0.06, 4.0],
        [3, 0.11, 0.0],
    ])->each(function (array $row): void {
        PriceForecast::query()->create([
            'starts_at' => now()->startOfHour()->addHours($row[0]),
            'ends_at' => now()->startOfHour()->addHours($row[0] + 1),
            'market_price_per_kwh' => $row[1],
            'solar_surplus_kwh' => $row[2],
            'source' => 'test',
        ]);
    });

    $this->artisan('app:evaluate-charging-strategy')
        ->assertSuccessful();

    $plan = ChargingPlan::query()->with('slots')->first();

    expect($plan)->not->toBeNull();
    expect($plan->slots->count())->toBeGreaterThanOrEqual(3);
    expect($plan->slots->count())->toBeLessThanOrEqual(4);
    expect($plan->slots->firstWhere('allocated_solar_energy_kwh', '>', 0))->not->toBeNull();
});

test('strategy command can build a short test horizon plan', function () {
    ChargingSetting::query()->create([
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
        'grid_day_rate_per_kwh' => 0.09,
        'grid_night_rate_per_kwh' => 0.03,
        'grid_weekend_rate_per_kwh' => 0.04,
        'day_rate_starts_at' => '07:00',
        'night_rate_starts_at' => '22:00',
    ]);

    $start = now()->startOfMinute()->subMinutes(now()->minute % 15)->addMinutes(15);

    collect([
        [0, 0.08, 0.0],
        [15, 0.04, 1.5],
        [30, 0.09, 0.0],
        [45, 0.12, 0.0],
    ])->each(function (array $row) use ($start): void {
        PriceForecast::query()->create([
            'starts_at' => $start->addMinutes($row[0]),
            'ends_at' => $start->addMinutes($row[0] + 15),
            'market_price_per_kwh' => $row[1],
            'solar_surplus_kwh' => $row[2],
            'source' => 'test',
        ]);
    });

    $this->artisan('app:evaluate-charging-strategy --horizon-minutes=45')
        ->assertSuccessful();

    $plan = ChargingPlan::query()->with('slots')->latest('generated_at')->first();

    expect($plan)->not->toBeNull();
    expect($plan->slots->count())->toBeGreaterThanOrEqual(3);
    expect($plan->slots->count())->toBeLessThanOrEqual(4);
    expect($plan->slots->every(fn ($slot) => $slot->starts_at->lessThan(now()->toImmutable()->addMinutes(45))))->toBeTrue();
});

test('strategy command imports home assistant forecasts before planning', function () {
    config()->set('home_assistant.url', 'http://ha.local');
    config()->set('home_assistant.token', 'secret');
    config()->set('home_assistant.entities', [
        'vehicle_soc' => 'sensor.leaf_battery_level',
        'nordpool' => 'sensor.nordpool_kwh_ee_eur_3_10_024',
        'solar_current_hour_east' => 'sensor.energy_current_hour',
        'solar_current_hour_west' => 'sensor.energy_current_hour_2',
        'solar_next_hour_east' => 'sensor.energy_next_hour',
        'solar_next_hour_west' => 'sensor.energy_next_hour_2',
        'charger_switch' => 'switch.c26634_charge_control',
        'charger_max_current' => 'number.c26634_maximum_current',
    ]);

    ChargingSetting::query()->create([
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

    Http::fake([
        'http://ha.local/api/states/sensor.leaf_battery_level' => Http::response([
            'entity_id' => 'sensor.leaf_battery_level',
            'state' => '55',
        ], 200),
        'http://ha.local/api/states/sensor.nordpool_kwh_ee_eur_3_10_024' => Http::response([
            'entity_id' => 'sensor.nordpool_kwh_ee_eur_3_10_024',
            'attributes' => [
                'price_in_cents' => true,
                'raw_today' => [
                    [
                        'start' => now()->startOfMinute()->subMinutes(now()->minute % 15)->toIso8601String(),
                        'end' => now()->startOfMinute()->subMinutes(now()->minute % 15)->addMinutes(15)->toIso8601String(),
                        'value' => 1.489,
                    ],
                    [
                        'start' => now()->startOfMinute()->subMinutes(now()->minute % 15)->addMinutes(15)->toIso8601String(),
                        'end' => now()->startOfMinute()->subMinutes(now()->minute % 15)->addMinutes(30)->toIso8601String(),
                        'value' => 1.2,
                    ],
                ],
                'raw_tomorrow' => [],
            ],
        ], 200),
        'http://ha.local/api/states' => Http::response([
            ['entity_id' => 'sensor.energy_current_hour', 'state' => '1.0'],
            ['entity_id' => 'sensor.energy_current_hour_2', 'state' => '0.8'],
            ['entity_id' => 'sensor.energy_next_hour', 'state' => '0.6'],
            ['entity_id' => 'sensor.energy_next_hour_2', 'state' => '0.4'],
            ['entity_id' => 'switch.c26634_charge_control', 'state' => 'off'],
            ['entity_id' => 'number.c26634_maximum_current', 'state' => '16'],
        ], 200),
    ]);

    $this->artisan('app:evaluate-charging-strategy --horizon-minutes=30')
        ->assertSuccessful();

    expect(PriceForecast::query()->where('source', 'home-assistant')->count())->toBe(2);
    expect(ChargingPlan::query()->count())->toBe(1);
});

test('strategy command supports one-off minimum soc and deadline overrides', function () {
    ChargingSetting::query()->create([
        'charger_name' => 'planner-test',
        'ha_charge_control_entity_id' => 'switch.c26634_charge_control',
        'ha_maximum_current_entity_id' => 'number.c26634_maximum_current',
        'battery_capacity_kwh' => 40,
        'current_soc_percent' => 50,
        'daily_minimum_soc_percent' => 55,
        'target_soc_percent' => 80,
        'daily_minimum_deadline' => '07:00',
        'charger_power_kw' => 4,
        'charger_min_current_amps' => 6,
        'charger_max_current_amps' => 16,
        'charger_efficiency' => 0.92,
        'grid_day_rate_per_kwh' => 0.09,
        'grid_night_rate_per_kwh' => 0.03,
        'grid_weekend_rate_per_kwh' => 0.04,
        'day_rate_starts_at' => '07:00',
        'night_rate_starts_at' => '22:00',
    ]);

    collect(range(0, 7))->each(function (int $index): void {
        PriceForecast::query()->create([
            'starts_at' => now()->startOfHour()->addHours($index),
            'ends_at' => now()->startOfHour()->addHours($index + 1),
            'market_price_per_kwh' => 0.05 + ($index * 0.01),
            'solar_surplus_kwh' => 0,
            'source' => 'test',
        ]);
    });

    $this->artisan('app:evaluate-charging-strategy --minimum-soc=70 --minimum-deadline=06:00')
        ->assertSuccessful();

    $plan = ChargingPlan::query()->latest('generated_at')->first();

    expect($plan)->not->toBeNull();
    expect((float) $plan->minimum_energy_kwh)->toBe(8.0);
    expect($plan->deadline_at->format('H:i'))->toBe('06:00');
});

test('strategy command selects the cheapest non-consecutive slots when that is enough to meet the target', function () {
    config()->set('charging.defaults.target_soc_percent', 75);
    config()->set('charging.defaults.daily_minimum_soc_percent', 70);
    config()->set('charging.defaults.charger_power_kw', 4);
    config()->set('charging.defaults.grid_day_rate_per_kwh', 0.01);
    config()->set('charging.defaults.grid_night_rate_per_kwh', 0.01);
    config()->set('charging.defaults.grid_weekend_rate_per_kwh', 0.01);

    ChargingSetting::query()->create([
        'charger_name' => 'planner-test',
        'ha_charge_control_entity_id' => 'switch.c26634_charge_control',
        'ha_maximum_current_entity_id' => 'number.c26634_maximum_current',
        'battery_capacity_kwh' => 40,
        'current_soc_percent' => 70,
        'daily_minimum_soc_percent' => 70,
        'target_soc_percent' => 75,
        'daily_minimum_deadline' => '07:00',
        'charger_power_kw' => 4,
        'charger_min_current_amps' => 6,
        'charger_max_current_amps' => 16,
        'charger_efficiency' => 1.0,
        'grid_day_rate_per_kwh' => 0.01,
        'grid_night_rate_per_kwh' => 0.01,
        'grid_weekend_rate_per_kwh' => 0.01,
        'day_rate_starts_at' => '07:00',
        'night_rate_starts_at' => '22:00',
    ]);

    $start = now()->startOfMinute()->subMinutes(now()->minute % 15);

    collect([
        [0, 0.30],
        [15, 0.05],
        [30, 0.28],
        [45, 0.04],
        [60, 0.27],
        [75, 0.03],
    ])->each(function (array $row) use ($start): void {
        PriceForecast::query()->create([
            'starts_at' => $start->addMinutes($row[0]),
            'ends_at' => $start->addMinutes($row[0] + 15),
            'market_price_per_kwh' => $row[1],
            'solar_surplus_kwh' => 0,
            'source' => 'test',
        ]);
    });

    $this->artisan('app:evaluate-charging-strategy --horizon-minutes=90')
        ->assertSuccessful();

    $selectedStarts = ChargingPlan::query()
        ->with('slots')
        ->latest('generated_at')
        ->first()
        ->slots
        ->pluck('starts_at')
        ->map(fn ($startsAt) => $startsAt->format('H:i'))
        ->all();

    expect($selectedStarts)->toBe([
        $start->addMinutes(45)->format('H:i'),
        $start->addMinutes(75)->format('H:i'),
    ]);
});
