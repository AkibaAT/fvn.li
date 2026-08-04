<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscordServerConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'discord_server_id',
        'notification_channel_id',
        'notification_format',
        'custom_template',
        'include_game_description',
        'include_thumbnail',
        'include_ratings',
        'ping_role_id',
        'routing_rules',
        'new_game_embed',
        'update_embed',
    ];

    protected $casts = [
        'include_game_description' => 'boolean',
        'include_thumbnail' => 'boolean',
        'include_ratings' => 'boolean',
        'routing_rules' => 'array',
        'new_game_embed' => 'array',
        'update_embed' => 'array',
    ];

    /**
     * Get the Discord server this config belongs to.
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(DiscordServer::class);
    }

    /**
     * Get the notification template for this server.
     */
    public function getNotificationTemplate(): string
    {
        if ($this->notification_format === 'custom' && $this->custom_template) {
            return $this->custom_template;
        }

        return $this->getDefaultTemplate($this->notification_format);
    }

    /**
     * Format a notification message.
     */
    public function formatNotification(Game $game, string $notificationType = 'update'): string
    {
        $template = $this->getNotificationTemplate();

        $replacements = [
            '{game_name}' => $game->name,
            '{game_url}' => $game->getPrimaryUrl() ?? '',
            '{game_description}' => $this->include_game_description ? substr($game->description, 0, 200) : '',
            '{game_rating}' => $this->include_ratings ? round($game->rating_score, 1) : '',
            '{notification_type}' => $notificationType,
            '{timestamp}' => now()->format('Y-m-d H:i:s'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Check if config is valid.
     */
    public function isValid(): bool
    {
        return $this->notification_channel_id !== null;
    }

    /**
     * Get default template for a format.
     */
    private function getDefaultTemplate(string $format): string
    {
        return match ($format) {
            'compact' => "**{game_name}** has been updated!\n{game_url}",
            'detailed' => "**{game_name}** Update\n\n{game_description}\n\nRating: {game_rating}\n{game_url}",
            default => "**{game_name}** has been updated!\n{game_url}",
        };
    }
}
