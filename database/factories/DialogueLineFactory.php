<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Character;
use App\Models\GameVersion;
use App\Models\UniqueDialogueText;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DialogueLine>
 */
class DialogueLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $languageCodes = ['eng', 'jpn', 'fra', 'deu', 'spa'];
        $filePaths = [
            'script.rpy',
            'chapter1/intro.rpy',
            'chapter2/main.rpy',
            'characters/dialogue.rpy',
        ];

        $dialogueTexts = [
            'Hello, how are you today?',
            'I never expected to see you here.',
            'The weather is quite nice, isn\'t it?',
            'Let me tell you a story...',
            'This is an important decision.',
            'I understand your feelings.',
            'What do you think we should do?',
        ];

        return [
            'game_version_id' => GameVersion::factory(),
            'character_id' => Character::factory(),
            'iso_code' => fake()->randomElement($languageCodes),
            'file_path' => fake()->randomElement($filePaths),
            'line_number' => fake()->numberBetween(1, 1000),
            'text_id' => function () use ($dialogueTexts) {
                $text = fake()->randomElement($dialogueTexts);

                return UniqueDialogueText::factory()->create([
                    'text_content' => $text,
                    'text_hash' => md5($text),
                ])->id;
            },
            'context' => fake()->optional()->word(),
        ];
    }

    /**
     * Create a dialogue line in English.
     */
    public function english(): static
    {
        return $this->state([
            'iso_code' => 'eng',
        ]);
    }

    /**
     * Create a dialogue line in Japanese.
     */
    public function japanese(): static
    {
        return $this->state([
            'iso_code' => 'jpn',
        ]);
    }

    /**
     * Create a dialogue line with specific text content.
     */
    public function withText(string $textContent): static
    {
        return $this->state([
            'text_id' => function () use ($textContent) {
                return UniqueDialogueText::factory()->create([
                    'text_content' => $textContent,
                    'text_hash' => md5($textContent),
                ])->id;
            },
        ]);
    }

    /**
     * Create a dialogue line in a specific file.
     */
    public function inFile(string $filePath): static
    {
        return $this->state([
            'file_path' => $filePath,
        ]);
    }
}
