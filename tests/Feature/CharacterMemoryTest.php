<?php

use App\Jobs\FetchCallTranscript;
use App\Models\CallSession;
use App\Models\Character;
use App\Models\User;
use App\Services\CharacterPersona;
use App\Services\Runway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

test('session personas include memories of previous conversations', function () {
    $character = Character::factory()->ready()->create(['name' => 'Dot']);

    CallSession::factory()->create([
        'character_id' => $character->id,
        'status' => 'ended',
        'transcript' => [
            ['role' => 'user', 'content' => 'My favorite color is purple!'],
            ['role' => 'assistant', 'content' => 'Purple is beautiful, just like a sunset!'],
        ],
    ]);

    $persona = CharacterPersona::composeForSession($character);

    expect($persona)
        ->toContain('You remember your earlier chats')
        ->toContain('My favorite color is purple!')
        ->toContain('Your name is Dot');
});

test('session personas mention the artist\'s other characters', function () {
    $user = User::factory()->create();
    $character = Character::factory()->ready()->for($user)->create(['name' => 'Dot']);
    Character::factory()->ready()->for($user)->create([
        'name' => 'Nora the Fairy',
        'personality' => 'A cute fairy who loves flying.',
    ]);
    Character::factory()->for($user)->create(['name' => 'Unborn', 'runway_avatar_id' => null]);

    $persona = CharacterPersona::composeForSession($character);

    expect($persona)
        ->toContain('Nora the Fairy')
        ->not->toContain('Unborn');
});

test('session personas without history have no memory section', function () {
    $character = Character::factory()->ready()->create();

    expect(CharacterPersona::composeForSession($character))
        ->not->toContain('You remember your earlier chats');
});

test('the transcript job stores conversation lines', function () {
    $callSession = CallSession::factory()->claimed()->create();

    Http::fake([
        "api.dev.runwayml.com/v1/avatar_conversations/{$callSession->runway_session_id}" => Http::response([
            'id' => $callSession->runway_session_id,
            'status' => 'ended',
            'transcript' => [
                ['role' => 'assistant', 'content' => 'Hi there!', 'timestamp' => null],
                ['role' => 'user', 'content' => 'Tell me a joke', 'timestamp' => null],
                ['role' => 'assistant', 'content' => null, 'timestamp' => null],
            ],
        ]),
    ]);

    (new FetchCallTranscript($callSession))->handle(app(Runway::class));

    expect($callSession->refresh()->transcript)->toBe([
        ['role' => 'assistant', 'content' => 'Hi there!'],
        ['role' => 'user', 'content' => 'Tell me a joke'],
    ]);
});

test('ending a session queues the transcript fetch', function () {
    Queue::fake();
    config(['services.relay.token' => 'test-relay-token']);
    $callSession = CallSession::factory()->claimed()->create();

    $this->postJson(route('relay.sessions.end', $callSession->runway_session_id), [], ['X-Relay-Token' => 'test-relay-token'])
        ->assertNoContent();

    Queue::assertPushed(FetchCallTranscript::class);
});

test('call sessions are created with the memory-enriched persona', function () {
    $character = Character::factory()->ready()->create();
    CallSession::factory()->create([
        'character_id' => $character->id,
        'status' => 'ended',
        'transcript' => [['role' => 'user', 'content' => 'I live in Phoenix']],
    ]);

    Http::fake([
        'api.dev.runwayml.com/v1/realtime_sessions' => Http::response(['id' => 'session-1']),
    ]);

    $this->actingAs($character->user)
        ->postJson(route('characters.call-sessions.store', $character))
        ->assertCreated();

    Http::assertSent(fn ($request) => str_contains($request['personality'] ?? '', 'I live in Phoenix'));
});

test('session creation falls back to the baked-in persona when the override is rejected', function () {
    $character = Character::factory()->ready()->create();

    Http::fake([
        'api.dev.runwayml.com/v1/realtime_sessions' => Http::sequence()
            ->push(['error' => 'This text cannot be used.'], 400)
            ->push(['id' => 'session-2']),
    ]);

    $this->actingAs($character->user)
        ->postJson(route('characters.call-sessions.store', $character))
        ->assertCreated();

    Http::assertSentCount(2);
});
