<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasGameAttributes
{
    /**
     * Get available platform options for additional links
     */
    public static function getAvailablePlatforms(): array
    {
        return [
            'windows' => 'Windows',
            'mac' => 'Mac',
            'linux' => 'Linux',
            'android' => 'Android',
            'ios' => 'iOS',
            'web' => 'Web',
            'other' => 'Other',
        ];
    }

    /**
     * Protect first_visible_at from being accidentally overwritten.
     * Only allow setting if it's currently null.
     */
    public function setFirstVisibleAtAttribute($value): void
    {
        // Only allow setting first_visible_at if it's currently null
        // This prevents accidental overwrites of the original timestamp
        if ($this->attributes['first_visible_at'] ?? true) {
            $this->attributes['first_visible_at'] = $value;
        }
    }

    public function getAdditionalLinksAttribute($value): array
    {
        $now = Carbon::now();

        return array_values(array_filter($this->sortAdditionalLinks($value), function ($link) use ($now) {
            if (empty($link['release_at'])) {
                return true;
            }

            try {
                return $now->gte(Carbon::parse($link['release_at']));
            } catch (Exception) {
                return true;
            }
        }));
    }

    public function getAllAdditionalLinks(): array
    {
        return $this->sortAdditionalLinks($this->attributes['additional_links'] ?? null);
    }

    public function hasAdditionalLinks(): bool
    {
        return ! empty($this->additional_links);
    }

    private function sortAdditionalLinks($value): array
    {
        if (! $value) {
            return [];
        }

        $links = is_string($value) ? json_decode($value, true) : $value;
        if (! is_array($links)) {
            return [];
        }

        usort($links, function ($a, $b) {
            $orderA = $a['sort_order'] ?? 0;
            $orderB = $b['sort_order'] ?? 0;

            return $orderA === $orderB
                ? ($a['id'] ?? 0) <=> ($b['id'] ?? 0)
                : $orderA <=> $orderB;
        });

        return $links;
    }

    /**
     * Get the devlog attribute from latest version
     */
    protected function devlog(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->latestVersion?->devlog
        );
    }

    /**
     * Get the rating attribute
     */
    protected function rating(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attributes['rating_score'] ?? null
        );
    }

    /**
     * Get the rating count attribute
     */
    protected function ratingCount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attributes['rating_count'] ?? 0
        );
    }

    /**
     * Get the platforms attribute from latest version
     */
    protected function platforms(): Attribute
    {
        return Attribute::make(
            get: fn () => [
                'windows' => $this->latestVersion?->is_windows ?? false,
                'linux' => $this->latestVersion?->is_linux ?? false,
                'mac' => $this->latestVersion?->is_mac ?? false,
                'android' => $this->latestVersion?->is_android ?? false,
                'web' => $this->latestVersion?->is_web ?? false,
            ],
        );
    }
}
