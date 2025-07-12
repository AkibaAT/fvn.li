<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AdditionRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AdditionRequest>
 */
class AdditionRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $url = 'https://' . $this->faker->userName() . '.itch.io/' . $this->faker->slug();

        return [
            'itch_url' => $url,
            'normalized_url' => AdditionRequest::normalizeUrl($url),
            'status' => AdditionRequest::STATUS_PENDING,
            'rejection_reason' => null,
            'game_id' => null,
            'reviewed_at' => null,
            'reviewed_by' => null,
        ];
    }

    /**
     * Indicate that the request is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AdditionRequest::STATUS_APPROVED,
            'reviewed_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * Indicate that the request is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AdditionRequest::STATUS_REJECTED,
            'rejection_reason' => $this->faker->sentence(),
            'reviewed_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ]);
    }
}
