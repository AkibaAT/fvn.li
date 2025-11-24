<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserGameProgress>
 */
class UserGameProgressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statuses = ['reading', 'completed', 'plan_to_read', 'on_hold', 'dropped'];
        $status = fake()->randomElement($statuses);

        // Create game first, then version for that game to maintain foreign key integrity
        $game = Game::factory()->create();
        $version = GameVersion::factory()->create(['game_id' => $game->id]);

        return [
            'user_id' => User::factory(),
            'game_id' => $game->id,
            'game_version_id' => $version->id,
            'started_at' => $status !== 'plan_to_read' ? fake()->dateTimeBetween('-1 year', 'now') : null,
            'completed_at' => $status === 'completed' ? fake()->dateTimeBetween('-6 months', 'now') : null,
            'personal_notes' => fake()->optional(0.3)->paragraph(),
            'status' => $status,
            'receive_updates' => fake()->boolean(70), // 70% chance of receiving updates
        ];
    }

    /**
     * Indicate that the user is currently reading the game.
     */
    public function reading(): static
    {
        return $this->state([
            'status' => 'reading',
            'started_at' => fake()->dateTimeBetween('-3 months', 'now'),
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the user has completed the game.
     */
    public function completed(): static
    {
        return $this->state([
            'status' => 'completed',
            'started_at' => fake()->dateTimeBetween('-6 months', '-1 month'),
            'completed_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * Indicate that the user plans to read the game.
     */
    public function planToRead(): static
    {
        return $this->state([
            'status' => 'plan_to_read',
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the user has the game on hold.
     */
    public function onHold(): static
    {
        return $this->state([
            'status' => 'on_hold',
            'started_at' => fake()->dateTimeBetween('-6 months', '-1 month'),
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the user has dropped the game.
     */
    public function dropped(): static
    {
        return $this->state([
            'status' => 'dropped',
            'started_at' => fake()->dateTimeBetween('-6 months', '-1 month'),
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the user wants to receive updates.
     */
    public function receivingUpdates(): static
    {
        return $this->state([
            'receive_updates' => true,
        ]);
    }

    /**
     * Indicate that the user does not want to receive updates.
     */
    public function notReceivingUpdates(): static
    {
        return $this->state([
            'receive_updates' => false,
        ]);
    }
}
