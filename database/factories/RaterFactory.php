<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Rater;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rater>
 */
class RaterFactory extends Factory
{
    protected $model = Rater::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'itch_id' => fake()->randomNumber(6),
            'name' => fake()->userName(),
        ];
    }
}

