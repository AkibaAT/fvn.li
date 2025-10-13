<?php

declare(strict_types=1);

namespace App\Traits;

trait HasDefaultSort
{
    protected const string DEFAULT_SORT_FIELD = 'first_visible_at';

    protected const string DEFAULT_SORT_DIRECTION = 'desc';

    public static function getDefaultSortField(): string
    {
        return self::DEFAULT_SORT_FIELD;
    }

    public static function getDefaultSortDirection(): string
    {
        return self::DEFAULT_SORT_DIRECTION;
    }

    public static function isDefaultSort(string $sortField, string $sortDirection): bool
    {
        return $sortField === self::DEFAULT_SORT_FIELD && $sortDirection === self::DEFAULT_SORT_DIRECTION;
    }
}
