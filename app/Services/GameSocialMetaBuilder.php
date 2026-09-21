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
        $author = $game->authors ? trim(strip_tags($game->authors)) : null;
        $description = $game->description ?: "Discover {$game->effective_name} on fvn.li - Visual Novel Database and Analytics";
        $image = $game->getThumbnailUrl('default') ?? asset(config('social.images.default'));
        $platforms = $this->platforms($game);
        $tags = $game->tags->pluck('name')->toArray();

        return new MetaTags(
            title: $title,
            browserTitle: $this->browserTitle($title, $author),
            socialTitle: $title,
            description: $this->description($game, $description, $platforms, $reviews, $englishStats),
            image: $image,
            url: route('games.show', $game),
            type: 'article',
            noindex: ! $game->is_visible,
            isAdult: $game->is_nsfw,
            publishedTime: $game->initially_published_at?->toIso8601String(),
            modifiedTime: $this->modifiedAt($game),
            author: $author,
            section: 'Visual Novels',
            tags: $tags,
            structuredData: [
                '@graph' => [
                    $this->videoGame($game, $image, $tags, $platforms, $author),
                    $this->breadcrumbs($game),
                ],
            ],
            twitterCard: 'summary_large_image',
            siteName: 'FVN.li',
            locale: 'en_US',
        );
    }

    private function description(Game $game, string $description, array $platforms, $reviews, ?array $englishStats): string
    {
        if ($game->authors) {
            $description .= ' by ' . strip_tags($game->authors);
        }
        if ($game->status) {
            $description .= " ({$game->status})";
        }
        if ($englishStats && isset($englishStats['words']) && is_numeric($englishStats['words']) && (int) $englishStats['words'] > 0) {
            $description .= ' - ' . number_format((int) $englishStats['words']) . ' words';
        }
        if (! empty($platforms)) {
            $description .= ' - Available on: ' . implode(', ', $platforms);
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

    private function browserTitle(string $title, ?string $author): string
    {
        $withAuthor = $author !== null && $author !== '' ? "{$title} by {$author}" : $title;

        return mb_strlen($withAuthor) <= 45
            ? "{$withAuthor} – Furry Visual Novel"
            : (mb_strlen($title) <= 45 ? "{$title} – Furry Visual Novel" : $title);
    }

    private function videoGame(Game $game, string $image, array $tags, array $platforms, ?string $author): array
    {
        return array_filter([
            '@type' => 'VideoGame',
            'name' => $game->effective_name,
            'description' => $game->description,
            'image' => $image,
            'url' => route('games.show', $game),
            'author' => $author ? [
                '@type' => 'Organization',
                'name' => $author,
            ] : null,
            'datePublished' => $game->initially_published_at?->toIso8601String(),
            'dateModified' => $this->modifiedAt($game),
            'genre' => $tags,
            'gamePlatform' => $platforms ?: null,
            'applicationCategory' => 'GameApplication',
            'operatingSystem' => $platforms ? implode(', ', $platforms) : null,
            'offers' => array_filter([
                '@type' => 'Offer',
                'price' => $game->is_paid ? ($game->current_price ?? 0) : 0,
                'priceCurrency' => $game->currency ?: 'USD',
                'availability' => 'https://schema.org/InStock',
                'url' => $game->primary_url,
            ], fn ($value) => $value !== null),
            'aggregateRating' => $game->rating_score && $game->rating_count ? [
                '@type' => 'AggregateRating',
                'ratingValue' => round($game->rating_score, 2),
                'ratingCount' => $game->rating_count,
                'bestRating' => 5,
                'worstRating' => 1,
            ] : null,
        ], fn ($value) => $value !== null);
    }

    private function modifiedAt(Game $game): ?string
    {
        return collect([$game->latestVersion?->published_at, $game->custom_page_updated_at, $game->initially_published_at])->filter()->max()?->toIso8601String();
    }

    private function breadcrumbs(Game $game): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Visual Novels', 'item' => route('games.index')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $game->effective_name, 'item' => route('games.show', $game)],
            ],
        ];
    }
}
