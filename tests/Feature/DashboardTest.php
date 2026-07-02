<?php

use App\Models\Character;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    Character::factory()->ready()->for($user)->create();
    Character::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('characters', 2)
            ->where('stats.characters', 2)
            ->where('stats.ready', 1)
            ->where('stats.calls', 0));
});
