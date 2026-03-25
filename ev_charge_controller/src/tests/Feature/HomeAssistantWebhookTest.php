<?php

use App\Services\ChargingConnectionService;
use Carbon\CarbonImmutable;

test('connection webhook rejects an invalid secret', function () {
    config()->set('charging.webhooks.connection_secret', 'expected-secret');

    $response = $this->postJson('/ha/webhook/wrong-secret/connection', [
        'status' => 'connected',
    ]);

    $response->assertForbidden();
});

test('connection webhook forwards connected state to the service', function () {
    config()->set('charging.webhooks.connection_secret', 'expected-secret');

    $mock = Mockery::mock(ChargingConnectionService::class);
    $mock->shouldReceive('handle')
        ->once()
        ->with(true, Mockery::type(CarbonImmutable::class))
        ->andReturn([
            'state' => 'connected',
            'message' => 'Vehicle connected. Rebuilt plan with 3 slots.',
        ]);

    $this->app->instance(ChargingConnectionService::class, $mock);

    $response = $this->postJson('/ha/webhook/expected-secret/connection', [
        'status' => 'connected',
    ]);

    $response
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'connected' => true,
            'state' => 'connected',
        ]);
});
