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
