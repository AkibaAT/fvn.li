<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Game;
use App\Models\VnList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VnListEntry>
 */
class VnListEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vn_list_id' => VnList::factory(),
            'game_id' => Game::factory(),
            'sort_order' => fake()->numberBetween(10, 1000),
            // Note: 'notes' column doesn't exist in database, only 'private_notes'
            'private_notes' => fake()->optional(0.2)->paragraph(),
        ];
    }

    /**
     * Indicate that the entry has private notes.
     */
    public function withPrivateNotes(): static
    {
        return $this->state([
            'private_notes' => fake()->paragraph(),
        ]);
    }
}

