<?php

use App\Services\HomeAssistantService;
use App\Services\PriceForecastImportService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

test('price forecast importer stores nordpool quarter-hour rows with vat and near-term solar', function () {
    CarbonImmutable::setTestNow('2026-03-25 12:05:00+02:00');

    try {
        config()->set('home_assistant.url', 'http://ha.local');
        config()->set('home_assistant.token', 'secret');
        config()->set('home_assistant.entities', [
            'nordpool' => 'sensor.nordpool_kwh_ee_eur_3_10_024',
            'solar_current_hour_east' => 'sensor.energy_current_hour',
            'solar_current_hour_west' => 'sensor.energy_current_hour_2',
            'solar_next_hour_east' => 'sensor.energy_next_hour',
            'solar_next_hour_west' => 'sensor.energy_next_hour_2',
        ]);
        config()->set('charging.rates.vat_rate', 0.24);

        Http::fake([
            'http://ha.local/api/states' => Http::response([
                [
                    'entity_id' => 'sensor.energy_current_hour',
                    'state' => '1.2',
                ],
                [
                    'entity_id' => 'sensor.energy_current_hour_2',
                    'state' => '0.8',
                ],
                [
                    'entity_id' => 'sensor.energy_next_hour',
                    'state' => '0.6',
                ],
                [
                    'entity_id' => 'sensor.energy_next_hour_2',
                    'state' => '0.4',
                ],
            ], 200),
            'http://ha.local/api/states/sensor.nordpool_kwh_ee_eur_3_10_024' => Http::response([
                'entity_id' => 'sensor.nordpool_kwh_ee_eur_3_10_024',
                'attributes' => [
                    'price_in_cents' => true,
                    'raw_today' => [
                        [
                            'start' => '2026-03-25T12:00:00+02:00',
                            'end' => '2026-03-25T12:15:00+02:00',
                            'value' => 1.489,
                        ],
                        [
                            'start' => '2026-03-25T13:00:00+02:00',
                            'end' => '2026-03-25T13:15:00+02:00',
                            'value' => 2.0,
                        ],
                    ],
                    'raw_tomorrow' => [],
                ],
            ], 200),
        ]);

        $imported = app(PriceForecastImportService::class)
            ->importFromHomeAssistant(app(HomeAssistantService::class), now()->setTime(12, 5)->toImmutable());

        expect($imported)->toHaveCount(2);
        expect($imported->first()->market_price_per_kwh)->toBe(0.0185);
        expect($imported->first()->solar_surplus_kwh)->toBe(0.5);
        expect($imported->last()->solar_surplus_kwh)->toBe(0.25);
    } finally {
        CarbonImmutable::setTestNow();
    }
});
