<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VnList>
 */
class VnListFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['reading', 'completed', 'plan_to_read', 'on_hold', 'dropped', 'custom'];

        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(3, true), // Generate unique list names
            'description' => fake()->optional()->paragraph(),
            'type' => fake()->randomElement($types),
            'is_default' => false,
            'is_public' => fake()->boolean(30), // 30% chance of being public
        ];
    }

    /**
     * Create a default system list.
     */
    public function default(): static
    {
        return $this->state([
            'is_default' => true,
            'is_public' => false,
        ]);
    }

    /**
     * Create a public list.
     */
    public function public(): static
    {
        return $this->state([
            'is_public' => true,
        ]);
    }

    /**
     * Create a reading list.
     */
    public function reading(): static
    {
        return $this->state([
            'name' => 'Currently Reading',
            'type' => 'reading',
        ]);
    }

    /**
     * Create a completed list.
     */
    public function completed(): static
    {
        return $this->state([
            'name' => 'Completed',
            'type' => 'completed',
        ]);
    }
}
