<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Support\Seo\MetaTags;

class GameSocialMetaBuilder
{
    public function build(Game $game, $reviews, ?array $englishStats = null): MetaTags
    {
        $title = $game->effective_name;
        $description = $game->description ?: "Discover {$game->effective_name} on fvn.li - Visual Novel Database and Analytics";
        $image = $game->getThumbnailUrl('default') ?? asset(config('social.images.default'));
        $platforms = $this->platforms($game);
        $tags = $game->tags->pluck('name')->toArray();

        return new MetaTags(
            title: $title,
            browserTitle: $title,
            socialTitle: $title,
            description: $this->description($game, $description, $platforms, $reviews, $englishStats),
            image: $image,
            url: route('games.show', $game),
            type: 'article',
            noindex: ! $game->is_visible,
            publishedTime: $game->initially_published_at?->toIso8601String(),
            modifiedTime: $game->latest_version_published_at?->toIso8601String() ?? $game->updated_at->toIso8601String(),
            author: $game->authors ? strip_tags($game->authors) : null,
            section: 'Visual Novels',
            tags: $tags,
            structuredData: $this->structuredData($game, $image, $tags, $platforms),
            twitterCard: 'summary_large_image',
            siteName: 'FVN.li',
            locale: 'en_US',
        );
    }

    private function description(Game $game, string $description, array $platforms, $reviews, ?array $englishStats): string
    {
        if ($game->authors) {
            $description .= ' by '.strip_tags($game->authors);
        }
        if ($game->status) {
            $description .= " ({$game->status})";
        }
        if ($englishStats && isset($englishStats['words']) && is_numeric($englishStats['words']) && (int) $englishStats['words'] > 0) {
            $description .= ' - '.number_format((int) $englishStats['words']).' words';
        }
        if (! empty($platforms)) {
            $description .= ' - Available on: '.implode(', ', $platforms);
        }
        if ($reviews->total() > 0) {
            $description .= " - {$reviews->total()} reviews";
        }

        return $description;
    }

    private function platforms(Game $game): array
    {
        $platforms = [];
        if (! $game->latestVersion) {
            return $platforms;
        }

        if ($game->latestVersion->is_windows) {
            $platforms[] = 'Windows';
        }
        if ($game->latestVersion->is_mac) {
            $platforms[] = 'macOS';
        }
        if ($game->latestVersion->is_linux) {
            $platforms[] = 'Linux';
        }
        if ($game->latestVersion->is_android) {
            $platforms[] = 'Android';
        }
        if ($game->latestVersion->is_web) {
            $platforms[] = 'Web';
        }

        return $platforms;
    }

    private function structuredData(Game $game, string $image, array $tags, array $platforms): array
    {
        return array_filter([
            '@type' => 'VideoGame',
            'name' => $game->name,
            'description' => $game->description,
            'image' => $image,
            'url' => route('games.show', $game),
            'author' => $game->authors ? [
                '@type' => 'Organization',
                'name' => strip_tags($game->authors),
            ] : null,
            'datePublished' => $game->initially_published_at?->toIso8601String(),
            'dateModified' => $game->latest_version_published_at?->toIso8601String() ?? $game->updated_at->toIso8601String(),
            'genre' => $tags,
            'gamePlatform' => $platforms,
            'offers' => $game->is_paid ? [
                '@type' => 'Offer',
                'price' => $game->current_price ?? $game->min_price,
                'priceCurrency' => 'USD',
                'availability' => 'https://schema.org/InStock',
            ] : null,
            'aggregateRating' => $game->rating_score ? [
                '@type' => 'AggregateRating',
                'ratingValue' => round($game->rating_score, 2),
                'ratingCount' => $game->rating_count,
                'bestRating' => 5,
                'worstRating' => 1,
            ] : null,
        ], fn ($value) => $value !== null);
    }
}
