<?php

use App\CharacterStatus;
use App\Jobs\ProcessCharacter;
use App\Models\Character;
use App\Services\Runway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

test('it generates a portrait and creates an avatar', function () {
    Storage::fake('public');
    Sleep::fake();

    $character = Character::factory()->create(['voice' => 'ruby']);

    Http::fake([
        'api.dev.runwayml.com/v1/text_to_image' => Http::response(['id' => 'task-1']),
        'api.dev.runwayml.com/v1/tasks/task-1' => Http::response([
            'id' => 'task-1',
            'status' => 'SUCCEEDED',
            'output' => ['https://cdn.runwayml.com/generated.png'],
        ]),
        'cdn.runwayml.com/*' => Http::response('fake-image-bytes'),
        'api.dev.runwayml.com/v1/avatars' => Http::response([
            'id' => 'avatar-1',
            'status' => 'READY',
        ]),
    ]);

    (new ProcessCharacter($character))->handle(app(Runway::class));

    $character->refresh();

    expect($character)
        ->status->toBe(CharacterStatus::Ready)
        ->runway_avatar_id->toBe('avatar-1')
        ->image_path->not->toBeNull();

    Storage::disk('public')->assertExists($character->image_path);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.dev.runwayml.com/v1/avatars'
            && $request['voice'] === ['type' => 'runway-live-preset', 'presetId' => 'ruby']
            && $request['referenceImage'] === 'https://cdn.runwayml.com/generated.png';
    });
});

test('it sends the drawing as a data uri reference image', function () {
    Storage::fake('public');
    Sleep::fake();

    Storage::disk('public')->put('drawings/test.png', 'drawing-bytes');
    $character = Character::factory()->fromDrawing()->create(['drawing_path' => 'drawings/test.png']);

    Http::fake([
        'api.dev.runwayml.com/v1/text_to_image' => Http::response(['id' => 'task-1']),
        'api.dev.runwayml.com/v1/tasks/task-1' => Http::response([
            'id' => 'task-1',
            'status' => 'SUCCEEDED',
            'output' => ['https://cdn.runwayml.com/generated.png'],
        ]),
        'cdn.runwayml.com/*' => Http::response('fake-image-bytes'),
        'api.dev.runwayml.com/v1/avatars' => Http::response(['id' => 'avatar-1', 'status' => 'READY']),
    ]);

    (new ProcessCharacter($character))->handle(app(Runway::class));

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.dev.runwayml.com/v1/text_to_image'
            && str_starts_with($request['referenceImages'][0]['uri'], 'data:image/png;base64,')
            && $request['referenceImages'][0]['tag'] === 'drawing'
            && str_contains($request['promptText'], '@drawing');
    });
});

test('it retries without the user personality when moderation rejects the text', function () {
    Storage::fake('public');
    Sleep::fake();

    $character = Character::factory()->create(['personality' => 'A cute fairy who loves playing with friends.']);

    Http::fake([
        'api.dev.runwayml.com/v1/text_to_image' => Http::response(['id' => 'task-1']),
        'api.dev.runwayml.com/v1/tasks/task-1' => Http::response([
            'id' => 'task-1',
            'status' => 'SUCCEEDED',
            'output' => ['https://cdn.runwayml.com/generated.png'],
        ]),
        'cdn.runwayml.com/*' => Http::response('fake-image-bytes'),
        'api.dev.runwayml.com/v1/avatars' => Http::sequence()
            ->push([
                'id' => 'avatar-rejected',
                'status' => 'FAILED',
                'failureReason' => 'This text cannot be used for an avatar. Please update the personality or start script.',
            ])
            ->push(['id' => 'avatar-clean', 'status' => 'READY']),
    ]);

    (new ProcessCharacter($character))->handle(app(Runway::class));

    expect($character->refresh())
        ->status->toBe(CharacterStatus::Ready)
        ->runway_avatar_id->toBe('avatar-clean');

    Http::assertSentCount(5);
    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.dev.runwayml.com/v1/avatars'
            && ! str_contains($request['personality'], 'cute fairy');
    });
});

test('it rides out rate limits with retries', function () {
    Storage::fake('public');
    Sleep::fake();

    Storage::disk('public')->put('characters/existing.png', 'portrait-bytes');
    $character = Character::factory()->create(['image_path' => 'characters/existing.png']);

    Http::fake([
        'api.dev.runwayml.com/v1/avatars' => Http::sequence()
            ->push(['error' => 'Too many requests.'], 429)
            ->push(['id' => 'avatar-1', 'status' => 'READY']),
    ]);

    (new ProcessCharacter($character))->handle(app(Runway::class));

    expect($character->refresh())->status->toBe(CharacterStatus::Ready);
});

test('it reuses a stored portrait instead of regenerating', function () {
    Storage::fake('public');
    Sleep::fake();

    Storage::disk('public')->put('characters/existing.png', 'portrait-bytes');
    $character = Character::factory()->create(['image_path' => 'characters/existing.png']);

    Http::fake([
        'api.dev.runwayml.com/v1/avatars' => Http::response(['id' => 'avatar-1', 'status' => 'READY']),
    ]);

    (new ProcessCharacter($character))->handle(app(Runway::class));

    expect($character->refresh())
        ->status->toBe(CharacterStatus::Ready)
        ->runway_avatar_id->toBe('avatar-1');

    Http::assertSentCount(1);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'text_to_image'));
});

test('it regenerates a close-up portrait when the avatar service rejects the image', function () {
    Storage::fake('public');
    Sleep::fake();

    $character = Character::factory()->fromDrawing()->create(['drawing_path' => 'drawings/kid.png']);
    Storage::disk('public')->put('drawings/kid.png', 'drawing-bytes');

    $rejection = [
        'id' => 'avatar-rejected',
        'status' => 'FAILED',
        'failureReason' => 'This text cannot be used for an avatar. Please update the personality or start script.',
    ];

    Http::fake([
        'api.dev.runwayml.com/v1/text_to_image' => Http::sequence()
            ->push(['id' => 'task-full'])
            ->push(['id' => 'task-closeup']),
        'api.dev.runwayml.com/v1/tasks/task-full' => Http::response([
            'id' => 'task-full', 'status' => 'SUCCEEDED', 'output' => ['https://cdn.runwayml.com/full.png'],
        ]),
        'api.dev.runwayml.com/v1/tasks/task-closeup' => Http::response([
            'id' => 'task-closeup', 'status' => 'SUCCEEDED', 'output' => ['https://cdn.runwayml.com/closeup.png'],
        ]),
        'cdn.runwayml.com/*' => Http::response('image-bytes'),
        'api.dev.runwayml.com/v1/avatars' => Http::sequence()
            ->push($rejection)
            ->push($rejection)
            ->push(['id' => 'avatar-closeup', 'status' => 'READY']),
    ]);

    (new ProcessCharacter($character))->handle(app(Runway::class));

    expect($character->refresh())
        ->status->toBe(CharacterStatus::Ready)
        ->runway_avatar_id->toBe('avatar-closeup');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.dev.runwayml.com/v1/text_to_image'
            && str_contains($request['promptText'], 'head-and-shoulders close-up');
    });

    Http::assertSent(fn ($request) => $request['referenceImage'] ?? null === 'https://cdn.runwayml.com/closeup.png');
});

test('it fails cleanly when the drawing file is missing', function () {
    Storage::fake('public');
    Sleep::fake();

    $character = Character::factory()->fromDrawing()->create(['drawing_path' => 'drawings/gone.png']);

    $job = new ProcessCharacter($character);

    try {
        $job->handle(app(Runway::class));
    } catch (RuntimeException $exception) {
        $job->failed($exception);
    }

    expect($character->refresh())
        ->status->toBe(CharacterStatus::Failed)
        ->failure_reason->toContain('drawing file is missing');
});

test('it marks the character failed when generation fails', function () {
    Storage::fake('public');
    Sleep::fake();

    $character = Character::factory()->create();

    Http::fake([
        'api.dev.runwayml.com/v1/text_to_image' => Http::response(['id' => 'task-1']),
        'api.dev.runwayml.com/v1/tasks/task-1' => Http::response([
            'id' => 'task-1',
            'status' => 'FAILED',
            'failure' => 'Content was moderated.',
        ]),
    ]);

    $job = new ProcessCharacter($character);

    try {
        $job->handle(app(Runway::class));
    } catch (RuntimeException $exception) {
        $job->failed($exception);
    }

    expect($character->refresh())
        ->status->toBe(CharacterStatus::Failed)
        ->failure_reason->toContain('Content was moderated.');
});
