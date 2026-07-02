<?php

use App\CharacterStatus;
use App\Jobs\ProcessCharacter;
use App\Models\Character;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('guests are redirected to the login page', function () {
    $this->get(route('characters.index'))->assertRedirect(route('login'));
});

test('users can view their characters', function () {
    $user = User::factory()->create();
    Character::factory()->ready()->for($user)->create(['name' => 'Sparkles']);

    $this->actingAs($user)
        ->get(route('characters.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('characters/index')
            ->has('characters', 1)
            ->where('characters.0.name', 'Sparkles'));
});

test('users can create a character from a drawing', function () {
    Storage::fake('public');
    Queue::fake();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('characters.store'), [
        'name' => 'Sparkles',
        'personality' => 'A brave dragon who loves tacos.',
        'voice' => 'ruby',
        'drawing' => UploadedFile::fake()->image('dragon.png', 800, 800),
    ]);

    $character = Character::sole();

    $response->assertRedirect(route('characters.show', $character));
    expect($character)
        ->name->toBe('Sparkles')
        ->user_id->toBe($user->id)
        ->drawing_path->not->toBeNull();

    Storage::disk('public')->assertExists($character->drawing_path);
    Queue::assertPushed(ProcessCharacter::class, fn (ProcessCharacter $job) => $job->character->is($character));
});

test('users can create a character from a prompt', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('characters.store'), [
        'name' => 'Sparkles',
        'personality' => 'A brave dragon who loves tacos.',
        'voice' => 'ruby',
        'prompt' => 'A purple dragon with tiny wings.',
    ])->assertRedirect();

    expect(Character::sole()->prompt)->toBe('A purple dragon with tiny wings.');
    Queue::assertPushed(ProcessCharacter::class);
});

test('a drawing or a prompt is required', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('characters.store'), [
        'name' => 'Sparkles',
        'personality' => 'A brave dragon.',
        'voice' => 'ruby',
    ])->assertSessionHasErrors(['drawing', 'prompt']);
});

test('failed characters can be retried', function () {
    Queue::fake();
    $character = Character::factory()->failed()->create();

    $this->actingAs($character->user)
        ->post(route('characters.retry', $character))
        ->assertRedirect(route('characters.show', $character));

    expect($character->refresh())
        ->status->toBe(CharacterStatus::Pending)
        ->failure_reason->toBeNull();

    Queue::assertPushed(ProcessCharacter::class);
});

test('only failed characters can be retried', function () {
    $character = Character::factory()->ready()->create();

    $this->actingAs($character->user)
        ->post(route('characters.retry', $character))
        ->assertConflict();
});

test('users can delete a character', function () {
    Storage::fake('public');
    Storage::disk('public')->put('drawings/d.png', 'drawing');
    Storage::disk('public')->put('characters/c.png', 'portrait');

    $character = Character::factory()->ready()->create([
        'drawing_path' => 'drawings/d.png',
        'image_path' => 'characters/c.png',
        'runway_avatar_id' => 'avatar-9',
    ]);

    Http::fake(['api.dev.runwayml.com/v1/avatars/avatar-9' => Http::response(null, 204)]);

    $this->actingAs($character->user)
        ->delete(route('characters.destroy', $character))
        ->assertRedirect(route('dashboard'));

    expect(Character::find($character->id))->toBeNull();
    Storage::disk('public')->assertMissing('drawings/d.png');
    Storage::disk('public')->assertMissing('characters/c.png');
    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && $request->url() === 'https://api.dev.runwayml.com/v1/avatars/avatar-9');
});

test('users cannot delete characters belonging to others', function () {
    $character = Character::factory()->ready()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('characters.destroy', $character))
        ->assertForbidden();

    expect(Character::find($character->id))->not->toBeNull();
});

test('users cannot view characters belonging to others', function () {
    $character = Character::factory()->ready()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('characters.show', $character))
        ->assertForbidden();
});
