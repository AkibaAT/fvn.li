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
            'game_id' => fake()->unique()->randomNumber(6),
            'slug' => fake()->slug(),
            'name' => fake()->words(3, true),
            'status' => fake()->randomElement(['Released', 'In Development', 'Abandoned', 'Canceled']),
            'is_visible' => true,
            'is_nsfw' => fake()->boolean(20),
            'description' => fake()->paragraph(),
            'url' => fake()->url(),
            'thumb_url' => fake()->imageUrl(),
            'game_engine' => "Ren'Py",
            'authors' => fake()->name(),
            'is_paid' => fake()->boolean(30),
            'has_demo' => fake()->boolean(40),
            'blur_screenshots' => fake()->boolean(20),
        ];
    }
}
