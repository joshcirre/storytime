<?php

use App\Models\CallSession;
use App\Support\RelayHeartbeat;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    config(['services.relay.token' => 'test-relay-token']);
});

test('the relay heartbeat endpoint requires a valid token', function () {
    $this->postJson(route('relay.heartbeat'), ['active_sessions' => 0, 'started_at' => now()->toIso8601String()])
        ->assertForbidden();
});

test('the relay heartbeat endpoint records liveness', function () {
    $this->postJson(route('relay.heartbeat'), [
        'active_sessions' => 3,
        'started_at' => now()->subMinutes(5)->toIso8601String(),
    ], ['X-Relay-Token' => 'test-relay-token'])->assertNoContent();

    $status = RelayHeartbeat::status();

    expect($status)
        ->online->toBeTrue()
        ->active_sessions->toBe(3)
        ->last_seen_seconds->toBeLessThanOrEqual(2);
});

test('the heartbeat endpoint validates its payload', function () {
    $this->postJson(route('relay.heartbeat'), ['active_sessions' => 'lots'], ['X-Relay-Token' => 'test-relay-token'])
        ->assertUnprocessable();
});

test('the status page reports offline with no heartbeat', function () {
    $this->get(route('relay-status'))
        ->assertOk()
        ->assertSee('Offline')
        ->assertSee('No heartbeat received yet');
});

test('the status json reports online after a fresh heartbeat', function () {
    RelayHeartbeat::record(activeSessions: 2, startedAt: now()->subMinute()->toIso8601String());
    CallSession::factory()->count(2)->create();

    $this->getJson(route('relay-status.data'))
        ->assertOk()
        ->assertJson([
            'online' => true,
            'active_sessions' => 2,
            'sessions_today' => 2,
        ]);
});

test('a stale heartbeat is reported as offline', function () {
    Cache::put('relay.heartbeat', [
        'active_sessions' => 1,
        'started_at' => now()->subHour()->toIso8601String(),
        'received_at' => now()->subMinute()->toIso8601String(),
    ], now()->addMinutes(5));

    $this->getJson(route('relay-status.data'))
        ->assertOk()
        ->assertJson(['online' => false]);
});

test('the status page is public', function () {
    $this->get(route('relay-status'))->assertOk();
});
