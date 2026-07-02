<?php

namespace Database\Factories;

use App\CharacterStatus;
use App\Models\Character;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Character>
 */
class CharacterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->firstName(),
            'personality' => fake()->sentence(),
            'voice' => 'ruby',
            'prompt' => fake()->sentence(),
            'drawing_path' => null,
            'image_path' => null,
            'runway_avatar_id' => null,
            'status' => CharacterStatus::Pending,
            'failure_reason' => null,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn (): array => [
            'status' => CharacterStatus::Ready,
            'image_path' => 'characters/'.fake()->uuid().'.png',
            'runway_avatar_id' => fake()->uuid(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => CharacterStatus::Failed,
            'failure_reason' => 'Something went wrong.',
        ]);
    }

    public function fromDrawing(): static
    {
        return $this->state(fn (): array => [
            'prompt' => null,
            'drawing_path' => 'drawings/'.fake()->uuid().'.png',
        ]);
    }
}
