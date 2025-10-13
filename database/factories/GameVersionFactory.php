<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Game;
use App\Models\GameVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameVersion>
 */
class GameVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'version' => fake()->numerify('#.#.#'),
            'published_at' => fake()->dateTimeThisYear(),
            'is_windows' => true,
            'is_linux' => fake()->boolean(50),
            'is_mac' => fake()->boolean(50),
            'is_android' => fake()->boolean(30),
            'is_web' => fake()->boolean(20),
        ];
    }

    /**
     * Indicate that the version is the latest.
     * Note: is_latest must be set after creation since it's not fillable.
     */
    public function latest(): static
    {
        return $this->afterCreating(function (GameVersion $version) {
            $version->is_latest = true;
            $version->save();
        });
    }
}
