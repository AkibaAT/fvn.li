<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Character;
use App\Models\GameVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VersionCharacterStats>
 */
class VersionCharacterStatsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $languageCodes = ['eng', 'jpn', 'fra', 'deu', 'spa', 'kor', 'zho'];

        return [
            'game_version_id' => GameVersion::factory(),
            'character_id' => Character::factory(),
            'iso_code' => fake()->randomElement($languageCodes),
            'blocks' => fake()->numberBetween(10, 500),
            'words' => fake()->numberBetween(100, 5000),
        ];
    }

    /**
     * Create stats for English language.
     */
    public function english(): static
    {
        return $this->state([
            'iso_code' => 'eng',
        ]);
    }

    /**
     * Create stats for Japanese language.
     */
    public function japanese(): static
    {
        return $this->state([
            'iso_code' => 'jpn',
        ]);
    }

    /**
     * Create stats with specific word/block counts.
     */
    public function withCounts(int $blocks, int $words): static
    {
        return $this->state([
            'blocks' => $blocks,
            'words' => $words,
        ]);
    }
}
