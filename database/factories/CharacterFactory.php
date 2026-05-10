<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Character;
use App\Models\Game;
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
        $characterNames = [
            'protagonist', 'hero', 'heroine', 'main_character',
            'love_interest', 'rival', 'mentor', 'friend',
            'antagonist', 'villain', 'side_character',
        ];

        $displayNames = [
            ['eng' => fake()->firstName(), 'jpn' => 'キャラクター'],
            ['eng' => fake()->name(), 'fra' => fake()->firstName()],
            ['eng' => fake()->firstName(), 'deu' => fake()->firstName()],
        ];

        $genders = ['male', 'female', 'non-binary', 'other', null];
        $species = ['human', 'elf', 'demon', 'angel', 'robot', 'alien', 'animal', 'dragon', null];
        $ages = ['18', '25', 'mid-20s', 'early 30s', '16', 'teen', 'young adult', 'adult', 'unknown', null];

        return [
            'game_id' => Game::factory(),
            'character_id' => fake()->randomElement($characterNames).'_'.fake()->unique()->numberBetween(1, 1000),
            'display_names' => fake()->randomElement($displayNames),
            'first_seen_in_version_id' => null,
            'last_seen_in_version_id' => null,
            'gender' => fake()->randomElement($genders),
            'species' => fake()->randomElement($species),
            'age' => fake()->randomElement($ages),
        ];
    }

    /**
     * Create a character with specific display names.
     */
    public function withDisplayNames(array $displayNames): static
    {
        return $this->state([
            'display_names' => $displayNames,
        ]);
    }

    /**
     * Create a character with display name corrections.
     */
    public function withCorrections(array $corrections): static
    {
        return $this->state([
            'display_name_corrections' => $corrections,
        ]);
    }

    /**
     * Create a narrator character.
     */
    public function narrator(): static
    {
        return $this->state([
            'character_id' => 'narrator',
            'display_names' => ['eng' => 'Narrator'],
        ]);
    }

    /**
     * Create a menu choice character.
     */
    public function menuChoice(): static
    {
        return $this->state([
            'character_id' => 'menu_choice',
            'display_names' => ['eng' => 'Menu Choice'],
        ]);
    }

    /**
     * Create a character with specific gender.
     */
    public function withGender(string $gender): static
    {
        return $this->state([
            'gender' => $gender,
        ]);
    }

    /**
     * Create a character with specific species.
     */
    public function withSpecies(string $species): static
    {
        return $this->state([
            'species' => $species,
        ]);
    }

    /**
     * Create a character with specific age.
     */
    public function withAge(string $age): static
    {
        return $this->state([
            'age' => $age,
        ]);
    }

    /**
     * Create a human character.
     */
    public function human(): static
    {
        return $this->state([
            'species' => 'human',
        ]);
    }

    /**
     * Create a female character.
     */
    public function female(): static
    {
        return $this->state([
            'gender' => 'female',
        ]);
    }

    /**
     * Create a male character.
     */
    public function male(): static
    {
        return $this->state([
            'gender' => 'male',
        ]);
    }
}
