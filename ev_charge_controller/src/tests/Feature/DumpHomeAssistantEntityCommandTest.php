<?php

use Illuminate\Support\Facades\Http;

test('dump home assistant entity command outputs configured entity json', function () {
    config()->set('home_assistant.url', 'http://ha.local');
    config()->set('home_assistant.token', 'secret');
    config()->set('home_assistant.entities', [
        'nordpool' => 'sensor.nordpool_kwh_ee_eur_3_10_024',
    ]);

    Http::fake([
        'http://ha.local/api/states/sensor.nordpool_kwh_ee_eur_3_10_024' => Http::response([
            'entity_id' => 'sensor.nordpool_kwh_ee_eur_3_10_024',
            'state' => '1.489',
            'attributes' => ['currency' => 'EUR'],
        ], 200),
    ]);

    $this->artisan('app:dump-home-assistant-entity nordpool --pretty')
        ->expectsOutputToContain('"entity_id": "sensor.nordpool_kwh_ee_eur_3_10_024"')
        ->assertSuccessful();
});
