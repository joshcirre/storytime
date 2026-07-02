<?php

use App\Models\CallSession;
use App\Models\Character;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('users can start a call session for a ready character', function () {
    $character = Character::factory()->ready()->create();

    Http::fake([
        'api.dev.runwayml.com/v1/realtime_sessions' => Http::response(['id' => 'session-1']),
    ]);

    $this->actingAs($character->user)
        ->postJson(route('characters.call-sessions.store', $character))
        ->assertCreated()
        ->assertJsonStructure(['callSessionId']);

    expect(CallSession::sole())
        ->runway_session_id->toBe('session-1')
        ->status->toBe('pending');

    Http::assertSent(function ($request) use ($character) {
        return $request->url() === 'https://api.dev.runwayml.com/v1/realtime_sessions'
            && $request['model'] === 'gwm1_avatars'
            && $request['avatar'] === ['type' => 'custom', 'avatarId' => $character->runway_avatar_id]
            && collect($request['tools'])->pluck('name')->all() === ['get_weather', 'tell_joke'];
    });
});

test('calls cannot start until the character is ready', function () {
    $character = Character::factory()->create();

    $this->actingAs($character->user)
        ->postJson(route('characters.call-sessions.store', $character))
        ->assertConflict();
});

test('users cannot start calls with characters belonging to others', function () {
    $character = Character::factory()->ready()->create();

    $this->actingAs(User::factory()->create())
        ->postJson(route('characters.call-sessions.store', $character))
        ->assertForbidden();
});

test('session status includes credentials once ready', function () {
    $callSession = CallSession::factory()->create();

    Http::fake([
        'api.dev.runwayml.com/v1/realtime_sessions/*' => Http::response([
            'id' => $callSession->runway_session_id,
            'status' => 'READY',
            'sessionKey' => 'key-123',
        ]),
    ]);

    $this->actingAs($callSession->character->user)
        ->getJson(route('call-sessions.show', $callSession))
        ->assertOk()
        ->assertJson([
            'status' => 'READY',
            'sessionId' => $callSession->runway_session_id,
            'sessionKey' => 'key-123',
        ]);
});
