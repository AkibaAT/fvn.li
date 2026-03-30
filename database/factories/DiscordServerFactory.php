<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DiscordServer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscordServer>
 */
class DiscordServerFactory extends Factory
{
    protected $model = DiscordServer::class;

    public function definition(): array
    {
        return [
            'discord_server_id' => (string) fake()->unique()->randomNumber(8),
            'discord_server_name' => fake()->company(),
            'owner_user_id' => User::factory(),
            'is_active' => true,
            'bot_joined_at' => now(),
            'available_channels' => [
                ['id' => (string) fake()->randomNumber(8), 'name' => 'general', 'type' => 0],
                ['id' => (string) fake()->randomNumber(8), 'name' => 'vn-updates', 'type' => 0],
            ],
            'channels_synced_at' => now(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function configured(): static
    {
        return $this->afterCreating(function (DiscordServer $server) {
            $server->config()->create([
                'discord_server_id' => $server->id,
                'notification_channel_id' => $server->available_channels[0]['id'] ?? '123456789',
                'notification_format' => 'compact',
            ]);
        });
    }
}
