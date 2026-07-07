<?php

namespace Database\Factories;

use App\Models\NodeDemoRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NodeDemoRequest>
 */
class NodeDemoRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => 'pending',
            'result' => null,
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => 'completed',
            'result' => ['runtime' => 'Node.js v22.0.0'],
            'completed_at' => now(),
        ]);
    }
}
