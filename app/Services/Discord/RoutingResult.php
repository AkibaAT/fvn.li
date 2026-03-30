<?php

declare(strict_types=1);

namespace App\Services\Discord;

class RoutingResult
{
    public bool $shouldSkip = false;

    public array $targetChannels = [];

    public function addChannel(string $channelId, ?array $embedOverride = null): void
    {
        $this->targetChannels[$channelId] = [
            'channel_id' => $channelId,
            'embed_override' => $embedOverride,
        ];
    }

    public function getTargetChannels(): array
    {
        return array_values($this->targetChannels);
    }

    public function hasChannels(): bool
    {
        return ! empty($this->targetChannels);
    }
}
