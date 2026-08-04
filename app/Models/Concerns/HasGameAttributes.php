<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasGameAttributes
{
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

    public function getAdditionalLinksAttribute($value): array
    {
        $now = Carbon::now();

        return array_values(array_filter($this->sortAdditionalLinks($value), function ($link) use ($now) {
            if (empty($link['release_at'])) {
                return true;
            }

            try {
                return $now->gte(Carbon::parse($link['release_at']));
            } catch (Exception $exception) {
                report($exception);

                return false;
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

    protected function devlog(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->latestVersion?->devlog
        );
    }

    protected function rating(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attributes['rating_score'] ?? null
        );
    }

    protected function ratingCount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attributes['rating_count'] ?? 0
        );
    }

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
}
