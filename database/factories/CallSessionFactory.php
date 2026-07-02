<?php

namespace Database\Factories;

use App\Models\CallSession;
use App\Models\Character;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallSession>
 */
class CallSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'character_id' => Character::factory()->ready(),
            'runway_session_id' => fake()->uuid(),
            'status' => 'pending',
            'claimed_at' => null,
        ];
    }

    public function claimed(): static
    {
        return $this->state(fn (): array => [
            'status' => 'claimed',
            'claimed_at' => now(),
        ]);
    }
}
