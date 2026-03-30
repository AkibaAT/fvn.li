<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DiscordNotificationHistory;
use App\Models\DiscordServer;
use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscordNotificationHistory>
 */
class DiscordNotificationHistoryFactory extends Factory
{
    protected $model = DiscordNotificationHistory::class;

    public function definition(): array
    {
        return [
            'discord_server_id' => DiscordServer::factory(),
            'game_id' => Game::factory(),
            'notification_type' => fake()->randomElement(['update', 'new_game', 'manual']),
            'channel_id' => (string) fake()->randomNumber(8),
            'delivery_status' => 'pending',
            'payload' => ['content' => 'Test notification'],
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'delivery_status' => 'sent',
            'sent_at' => now(),
            'message_id' => (string) fake()->randomNumber(8),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'delivery_status' => 'failed',
            'error_message' => fake()->sentence(),
        ]);
    }
}
