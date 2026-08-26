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
        $isFvnReview = ($row->source_platform ?? null) === 'fvn_li';

        return [
            'id' => (int) $row->id,
            'score' => (int) $row->rating,
            'created_at' => optional($row->published_at) ? (string) $row->published_at : null,
            'is_reviewed' => (bool) $row->is_reviewed,
            'review' => $this->sanitizeReview($row->review, $isFvnReview),
            'has_spoilers' => (bool) ($row->has_spoilers ?? false),
            'source_platform' => $row->source_platform ?? null,
            'game' => [
                'id' => (int) $row->game_id,
                'name' => $row->game_name,
                'slug' => $row->game_slug,
                'primary_url' => $this->extractPrimaryUrl($row->game_url, $row->game_platform),
                'platform' => $row->game_platform,
                'is_visible' => (bool) $row->game_is_visible,
            ],
            'user' => $row->user_id ? [
                'id' => (int) $row->user_id,
                'name' => $row->user_name,
                'avatar' => $row->user_avatar,
            ] : null,
            'rater' => $row->rater_id ? [
                'id' => (int) $row->rater_id,
                'name' => $row->rater_name,
                'external_platform' => $row->rater_platform,
            ] : null,
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
            'user' => ($author = $review->authorUser()) ? [
                'id' => $author->id,
                'name' => $author->name,
                'avatar' => $author->avatar,
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
            'review' => $this->sanitizeReview($review->review, $review->source_platform === 'fvn_li'),
            'published_at' => $review->published_at?->toISOString(),
            'is_reviewed' => $review->is_reviewed,
            'has_spoilers' => (bool) $review->has_spoilers,
            'source_platform' => $review->source_platform,
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
