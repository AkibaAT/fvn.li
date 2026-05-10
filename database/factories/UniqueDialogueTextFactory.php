<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\UniqueDialogueText;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UniqueDialogueText>
 */
class UniqueDialogueTextFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sampleTexts = [
            'Hello there! How are you doing today?',
            'I never expected to see you in a place like this.',
            'The weather has been quite strange lately, don\'t you think?',
            'Let me tell you a story about what happened yesterday.',
            'This is a very important decision that will affect everyone.',
            'I understand your feelings, but we need to think about this carefully.',
            'What do you think we should do in this situation?',
            'There\'s something I need to tell you before it\'s too late.',
            'The path ahead is uncertain, but we must keep moving forward.',
            'Sometimes the most difficult choices require the greatest courage.',
        ];

        $textContent = fake()->randomElement($sampleTexts);

        return [
            'text_content' => $textContent,
            'text_hash' => md5($textContent),
        ];
    }

    /**
     * Create a text with specific content.
     */
    public function withContent(string $content): static
    {
        return $this->state([
            'text_content' => $content,
            'text_hash' => md5($content),
        ]);
    }

    /**
     * Create a short text.
     */
    public function short(): static
    {
        $shortTexts = ['Yes.', 'No.', 'Maybe.', 'Okay.', 'Hello.', 'Goodbye.'];
        $content = fake()->randomElement($shortTexts);

        return $this->state([
            'text_content' => $content,
            'text_hash' => md5($content),
        ]);
    }

    /**
     * Create a long text.
     */
    public function long(): static
    {
        $content = fake()->paragraphs(3, true);

        return $this->state([
            'text_content' => $content,
            'text_hash' => md5($content),
        ]);
    }
}
