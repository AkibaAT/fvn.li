<?php

declare(strict_types=1);

namespace App\Traits;

trait HasSortableColumns
{
    public function getAvailableSortFields(): array
    {
        return static::AVAILABLE_SORT_FIELDS;
    }

    protected function getSortLabelLowercase(string $field): string
    {
        return strtolower($this->getSortLabel($field));
    }

    protected function getSortLabel(string $field): string
    {
        return match ($field) {
            'latest_version_published_at', 'published_at' => 'Release Date',
            'initially_published_at' => 'Initial Release',
            'first_visible_at' => 'Recently Added',
            'stats_words' => 'Word Count',
            'rating' => 'Rating',
            'rating_count' => 'Review Count',
            'name' => 'Name',
            'trending' => 'Trending',
            default => ucfirst(str_replace('_', ' ', $field))
        };
    }
}
