<?php

declare(strict_types=1);

namespace App\Services;

use Faker\Factory;
use Illuminate\Support\Str;

class RaterAliasService
{
    private \Faker\Generator $faker;

    public function __construct()
    {
        $this->faker = Factory::create();
    }

    public function generateAlias(): string
    {
        // Use a combination of Faker methods to create varied aliases
        $formats = [
            // Format 1: Adjective + Noun + Number
            fn () => $this->faker->word . $this->faker->word . rand(100, 9999),
            // Format 2: FirstName + City + Number
            fn () => $this->faker->firstName . $this->faker->city . rand(100, 9999),
            // Format 3: Color + LastName + Number
            fn () => $this->faker->safeColorName . $this->faker->lastName . rand(100, 9999),
            // Format 4: Company + Word + Number
            fn () => $this->faker->company . $this->faker->word . rand(100, 9999),
        ];

        $format = $formats[array_rand($formats)];
        $alias = $format();

        // Clean up the alias
        $alias = preg_replace('/[^a-zA-Z0-9]/', '', $alias);

        // Ensure first character is uppercase and reasonable length
        return Str::ucfirst(Str::limit($alias, 30, ''));
    }

    public function generateUniqueAlias(): string
    {
        // If regular generation fails, fall back to timestamp-based
        return $this->generateFallbackAlias();
    }

    private function generateFallbackAlias(): string
    {
        // Generate a completely unique identifier that's still somewhat readable
        $prefix = $this->faker->randomLetter . $this->faker->randomLetter;
        $timestamp = now()->format('ymdHis');
        $random = rand(1000, 9999);

        return Str::ucfirst($prefix) . $timestamp . $random;
    }
}
