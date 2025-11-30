<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'itch_id' => fake()->unique()->randomNumber(6),
            'name' => fake()->words(3, true),
            'status' => fake()->randomElement(['Released', 'In Development', 'Abandoned', 'Canceled']),
            'is_visible' => true,
            'is_nsfw' => fake()->boolean(20),
            'description' => fake()->paragraph(),
            'platform' => 'itch_io', // Default platform for tests
            'url' => [
                'itch_io' => fake()->url(),
            ],
            'thumb_url' => 'https://via.placeholder.com/630x500',
            'game_engine' => "Ren'Py",
            'authors' => fake()->name(),
            'is_paid' => fake()->boolean(30),
            'has_demo' => fake()->boolean(40),
        ];
    }
}
