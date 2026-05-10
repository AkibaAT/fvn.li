<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $providers = ['discord', 'google', 'steam', 'telegram', 'itchio'];
        $provider = fake()->randomElement($providers);

        return [
            'user_id' => User::factory(),
            'provider_name' => $provider,
            'provider_id' => fake()->unique()->numerify('########'),
            'token' => fake()->sha256(),
            'refresh_token' => fake()->optional()->sha256(),
            'token_expires_at' => fake()->optional()->dateTimeBetween('now', '+1 year'),
            'provider_data' => $this->getProviderData($provider),
        ];
    }

    /**
     * Create a Discord social account.
     */
    public function discord(): static
    {
        return $this->state([
            'provider_name' => 'discord',
            'provider_data' => [
                'username' => fake()->userName(),
                'discriminator' => fake()->numerify('####'),
                'avatar' => 'https://via.placeholder.com/128x128',
            ],
        ]);
    }

    /**
     * Create an itch.io social account.
     */
    public function itchio(): static
    {
        $username = fake()->userName();

        return $this->state([
            'provider_name' => 'itchio',
            'provider_data' => [
                'username' => $username,
                'url' => "https://{$username}.itch.io",
                'display_name' => fake()->name(),
            ],
        ]);
    }

    /**
     * Generate realistic provider data based on provider type.
     */
    private function getProviderData(string $provider): array
    {
        return match ($provider) {
            'discord' => [
                'username' => fake()->userName(),
                'discriminator' => fake()->numerify('####'),
                'avatar' => 'https://via.placeholder.com/128x128',
            ],
            'google' => [
                'email' => fake()->safeEmail(),
                'name' => fake()->name(),
                'avatar' => 'https://via.placeholder.com/96x96',
            ],
            'steam' => [
                'steamid' => fake()->numerify('##########'),
                'personaname' => fake()->userName(),
                'avatar' => 'https://via.placeholder.com/32x32',
            ],
            'telegram' => [
                'username' => fake()->userName(),
                'first_name' => fake()->firstName(),
                'last_name' => fake()->optional()->lastName(),
            ],
            'itchio' => [
                'username' => fake()->userName(),
                'url' => 'https://'.fake()->userName().'.itch.io',
                'display_name' => fake()->name(),
            ],
            default => [],
        };
    }
}
