<?php

declare(strict_types=1);

namespace App\Traits;

trait HasSortableColumns
{
    public function getAvailableSortFieldsWithLabels(): array
    {
        $fields = $this->getAvailableSortFields();
        $result = [];

        foreach ($fields as $field) {
            $result[$field] = $this->getSortLabel($field);
        }

        return $result;
    }

    public function getAvailableSortFields(): array
    {
        return static::AVAILABLE_SORT_FIELDS;
    }

    protected function getSortLabel(string $field): string
    {
        return match ($field) {
            'first_visible_at' => 'Recently Added',
            'latest_version_published_at' => 'Recently Updated',
            'initially_published_at' => 'Initial Release',
            'english_word_count', 'stats_words' => 'Word Count',
            'rating_count' => 'Rating Count',
            'name' => 'Name',
            'trending' => 'Trending',
            'rating' => 'Rating',
            'published_at' => 'Date',
            default => ucfirst(str_replace('_', ' ', $field))
        };
    }

    protected function getSortLabelLowercase(string $field): string
    {
        return strtolower($this->getSortLabel($field));
    }
}
