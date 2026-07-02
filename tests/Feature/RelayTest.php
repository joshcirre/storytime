<?php

use App\Models\CallSession;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.relay.token' => 'test-relay-token']);
});

function relayHeaders(): array
{
    return ['X-Relay-Token' => 'test-relay-token'];
}

test('relay routes reject requests without a valid token', function () {
    $this->getJson(route('relay.sessions.pending'))->assertForbidden();

    $this->getJson(route('relay.sessions.pending'), ['X-Relay-Token' => 'wrong'])->assertForbidden();
});

test('relay routes fail loudly when no token is configured', function () {
    config(['services.relay.token' => null]);

    $this->getJson(route('relay.sessions.pending'))->assertServiceUnavailable();
});

test('pending sessions are listed for the relay', function () {
    $pending = CallSession::factory()->create();
    CallSession::factory()->claimed()->create();
    CallSession::factory()->create(['created_at' => now()->subHour()]);

    $this->getJson(route('relay.sessions.pending'), relayHeaders())
        ->assertOk()
        ->assertJson(['sessions' => [$pending->runway_session_id]]);
});

test('the relay can claim and end sessions', function () {
    $callSession = CallSession::factory()->create();

    $this->postJson(route('relay.sessions.claim', $callSession->runway_session_id), [], relayHeaders())
        ->assertNoContent();

    expect($callSession->refresh())
        ->status->toBe('claimed')
        ->claimed_at->not->toBeNull();

    $this->postJson(route('relay.sessions.end', $callSession->runway_session_id), [], relayHeaders())
        ->assertNoContent();

    expect($callSession->refresh()->status)->toBe('ended');
});

test('the weather tool returns current conditions', function () {
    Http::fake([
        'geocoding-api.open-meteo.com/*' => Http::response([
            'results' => [[
                'name' => 'Phoenix',
                'admin1' => 'Arizona',
                'country' => 'United States',
                'latitude' => 33.44,
                'longitude' => -112.07,
            ]],
        ]),
        'api.open-meteo.com/*' => Http::response([
            'current' => [
                'temperature_2m' => 108.4,
                'apparent_temperature' => 111.2,
                'weather_code' => 0,
                'wind_speed_10m' => 5.3,
            ],
        ]),
    ]);

    $this->postJson(route('relay.tools.weather'), ['city' => 'Phoenix'], relayHeaders())
        ->assertOk()
        ->assertJson([
            'city' => 'Phoenix',
            'region' => 'Arizona',
            'conditions' => 'clear and sunny',
            'temperatureF' => 108,
        ]);
});

test('the weather tool returns a speakable error for unknown cities', function () {
    Http::fake([
        'geocoding-api.open-meteo.com/*' => Http::response(['results' => []]),
    ]);

    $this->postJson(route('relay.tools.weather'), ['city' => 'Nowhereville'], relayHeaders())
        ->assertOk()
        ->assertJsonPath('error', "I couldn't find a city called Nowhereville.");
});

test('the joke tool returns a joke', function () {
    Http::fake([
        'icanhazdadjoke.com/*' => Http::response(['joke' => 'Why did the dragon cross the road?']),
    ]);

    $this->postJson(route('relay.tools.joke'), [], relayHeaders())
        ->assertOk()
        ->assertJson(['joke' => 'Why did the dragon cross the road?']);
});
