<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Traits\HasSocialMetaTags;
use Illuminate\Pagination\LengthAwarePaginator;

final class SocialMetaTagsHarness
{
    use HasSocialMetaTags;

    public ?LengthAwarePaginator $games = null;

    public array $selectedStatuses = [];

    public array $selectedEngines = [];

    public array $selectedPlatforms = [];

    public array $selectedLanguages = [];

    public array $selectedGameJams = [];

    public array $selectedTags = [];

    public string $search = '';

    public bool $nsfw = false;

    public bool $sfw = false;

    public string $sortField = 'first_visible_at';

    public string $sortDirection = 'desc';

    public function title(): string
    {
        return $this->getMetaTitle();
    }

    public function description(): string
    {
        return $this->getMetaDescription();
    }

    public function image(): string
    {
        return $this->getMetaImage();
    }

    public function activeFilters(): bool
    {
        return $this->hasActiveFilters();
    }

    public function getSortLabel(string $field): string
    {
        return match ($field) {
            'rating_score' => 'Rating',
            default => ucfirst(str_replace('_', ' ', $field)),
        };
    }
}
