<?php

declare(strict_types=1);

namespace App\Models\Concerns;

trait HasGamePlatformSupport
{
    public function scopeByUrl($query, string $url)
    {
        return $query->where(function ($q) use ($url) {
            $q->whereRaw("url->>'itch_io' = ?", [$url])
                ->orWhereRaw("url->>'steam' = ?", [$url])
                ->orWhereRaw("url->>'other' = ?", [$url]);
        });
    }

    public function scopeOrByUrl($query, string $url)
    {
        return $query->orWhere(function ($q) use ($url) {
            $q->whereRaw("url->>'itch_io' = ?", [$url])
                ->orWhereRaw("url->>'steam' = ?", [$url])
                ->orWhereRaw("url->>'other' = ?", [$url]);
        });
    }

    public function scopeByUrlForPlatform($query, string $url, string $platform)
    {
        return $query->whereRaw("url->>'{$platform}' = ?", [$url]);
    }

    public function scopeFromPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeFromItchio($query)
    {
        return $query->where('platform', 'itch_io');
    }

    public function scopeFromSteam($query)
    {
        return $query->where('platform', 'steam');
    }

    public function scopeFromOther($query)
    {
        return $query->where('platform', 'other');
    }

    public function isItchioGame(): bool
    {
        return $this->platform === 'itch_io';
    }

    public function isSteamGame(): bool
    {
        return $this->platform === 'steam';
    }

    public function isOtherGame(): bool
    {
        return $this->platform === 'other';
    }

    public function getPlatformName(): string
    {
        return match ($this->platform) {
            'itch_io' => 'itch.io',
            'steam' => 'Steam',
            'other' => 'Other',
            default => 'Unknown',
        };
    }

    public function getUrlForPlatform(string $platform): ?string
    {
        $urls = $this->url ?? [];

        return $urls[$platform] ?? null;
    }

    public function getPrimaryUrl(): ?string
    {
        return $this->platform ? $this->getUrlForPlatform($this->platform) : null;
    }

    public function getAllUrls(): array
    {
        return $this->url ?? [];
    }

    public function setUrlForPlatform(string $platform, string $url): void
    {
        $urls = $this->url ?? [];
        $urls[$platform] = $url;
        $this->url = $urls;
    }

    public function hasUrlForPlatform(string $platform): bool
    {
        $urls = $this->url ?? [];

        return isset($urls[$platform]) && ! empty($urls[$platform]);
    }
}
