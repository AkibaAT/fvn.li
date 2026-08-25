<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Rating;

class RatingPresenter
{
    public function __construct(
        private readonly HtmlSanitizerService $sanitizer,
    ) {}

    public function indexRatingRow(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'score' => (int) $row->rating,
            'created_at' => optional($row->published_at) ? (string) $row->published_at : null,
            'is_reviewed' => (bool) $row->is_reviewed,
            'review' => $this->sanitizeReview($row->review),
            'game' => [
                'id' => (int) $row->game_id,
                'name' => $row->game_name,
                'slug' => $row->game_slug,
                'primary_url' => $this->extractPrimaryUrl($row->game_url, $row->game_platform),
                'platform' => $row->game_platform,
                'is_visible' => (bool) $row->game_is_visible,
            ],
            'rater' => [
                'id' => (int) $row->rater_id,
                'name' => $row->rater_name,
                'external_platform' => $row->rater_platform ?? 'itch_io',
            ],
        ];
    }

    public function raterRatingRow(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'rating' => (int) $row->rating,
            'published_at' => optional($row->published_at) ? (string) $row->published_at : null,
            'is_reviewed' => (bool) $row->is_reviewed,
            'review' => $this->sanitizeReview($row->review),
            'event_id' => $row->event_id,
            'is_visible' => (bool) $row->rating_is_visible,
            'game' => [
                'id' => (int) $row->game_id,
                'name' => $row->game_name,
                'slug' => $row->game_slug,
                'primary_url' => $this->extractPrimaryUrl($row->game_url, $row->game_platform),
                'platform' => $row->game_platform,
                'is_visible' => (bool) $row->game_is_visible,
            ],
        ];
    }

    public function reviewDetail(Rating $review): array
    {
        return [
            'id' => $review->id,
            'rating' => (int) $review->rating,
            'review' => $this->sanitizeReview($review->review, $review->source_platform === 'fvn_li'),
            'published_at' => $review->published_at?->toISOString(),
            'is_reviewed' => $review->is_reviewed,
            'has_spoilers' => (bool) $review->has_spoilers,
            'event_id' => $review->event_id,
            'source_platform' => $review->source_platform,
            'game' => $review->game ? [
                'id' => $review->game->id,
                'name' => $review->game->name,
                'slug' => $review->game->slug,
                'thumb_url' => $review->game->getThumbnailUrl('small'),
            ] : null,
            'user' => $review->user ? [
                'id' => $review->user->id,
                'name' => $review->user->name,
                'avatar' => $review->user->avatar,
            ] : null,
            'rater' => $review->rater ? [
                'id' => $review->rater->id,
                'name' => $review->rater->name,
                'external_platform' => $review->rater->external_platform,
            ] : null,
        ];
    }

    public function userReview(Rating $review): array
    {
        return [
            'id' => $review->id,
            'rating' => (int) $review->rating,
            'review' => $this->sanitizeReview($review->review, true),
            'published_at' => $review->published_at?->toISOString(),
            'is_reviewed' => $review->is_reviewed,
            'has_spoilers' => (bool) $review->has_spoilers,
            'game' => $review->game ? [
                'id' => $review->game->id,
                'name' => $review->game->name,
                'slug' => $review->game->slug,
                'thumb_url' => $review->game->getThumbnailUrl('small'),
            ] : null,
        ];
    }

    public function historyRatingRow(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'rating' => (int) $row->rating,
            'published_at' => optional($row->published_at) ? (string) $row->published_at : null,
            'is_visible' => (bool) $row->is_visible,
            'review' => $this->sanitizeReview($row->review),
            'event_id' => $row->event_id,
        ];
    }

    private function extractPrimaryUrl(?string $urlJson, ?string $platform): ?string
    {
        if (! $urlJson || ! $platform) {
            return null;
        }

        $urls = json_decode($urlJson, true);

        return $urls[$platform] ?? null;
    }

    private function sanitizeReview(?string $review, bool $isFvnReview = false): ?string
    {
        if (! $review) {
            return $review;
        }

        return $isFvnReview ? $this->sanitizer->sanitizeFvnReview($review) : $this->sanitizer->sanitizeReview($review);
    }
}
