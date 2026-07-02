<?php

use App\Jobs\ProcessCharacter;
use App\Models\Character;
use App\Models\User;
use Illuminate\Http\UploadedFile;
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

test('users cannot view characters belonging to others', function () {
    $character = Character::factory()->ready()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('characters.show', $character))
        ->assertForbidden();
});
