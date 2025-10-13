<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Language;
use App\Services\SocialImageService;
use Illuminate\Support\Str;

trait HasSocialMetaTags
{
    use HasDefaultSort;

    /**
     * Store meta tags data
     */
    protected array $metaTags = [];

    /**
     * Set meta tags for the current page
     */
    public function setMetaTags(array $metaTags): void
    {
        $this->metaTags = array_merge($this->metaTags, $metaTags);
    }

    /**
     * Get the current meta tags
     */
    public function getMetaTags(): array
    {
        return $this->metaTags;
    }

    protected function getMetaTitle(): string
    {
        $title = '';

        // Legacy Livewire-specific branch removed
        if (false) {
            $title = "{$this->totalRatingsCount} ratings";

            if ($this->visibleGamesRatingsCount < $this->totalRatingsCount) {
                $title .= " ({$this->visibleGamesRatingsCount} in listed games)";
            }

            return $title;
        }

        if (method_exists($this, 'getHeading')) {
            $totalRecords = $this->getAllTableRecordsCount();
            $title = "{$totalRecords} " . Str::plural(rtrim(strtolower($this->getHeading()), 's'), $totalRecords);
        }

        // For list-like views with a games paginator/collection
        if (property_exists($this, 'games')) {
            $totalRecords = $this->games->total();
            $title = "{$totalRecords} FVNs";

            // Add filter information
            $filters = [];

            if (property_exists($this, 'selectedStatuses') && ! empty($this->selectedStatuses)) {
                $statuses = array_map('strtolower', $this->selectedStatuses);
                $filters[] = implode('/', $statuses);
            }

            if (property_exists($this, 'selectedEngines') && ! empty($this->selectedEngines)) {
                $engines = array_map(fn ($e) => "made with {$e}", $this->selectedEngines);
                $filters[] = implode(' and ', $engines);
            }

            if (property_exists($this, 'selectedPlatforms') && ! empty($this->selectedPlatforms)) {
                $platforms = array_map(fn ($p) => 'for ' . ucfirst($p),
                    $this->selectedPlatforms);
                $filters[] = implode(' and ', $platforms);
            }

            if (property_exists($this, 'selectedLanguages') && ! empty($this->selectedLanguages)) {
                $languages = Language::whereIn('id', $this->selectedLanguages)
                    ->pluck('ref_name')
                    ->map(fn ($lang) => "in {$lang}")
                    ->implode(' and ');
                $filters[] = $languages;
            }

            if (property_exists($this, 'nsfw') && $this->nsfw) {
                $filters[] = 'NSFW';
            }

            if (property_exists($this, 'search') && $this->search) {
                $filters[] = "matching '{$this->search}'";
            }

            if (! empty($filters)) {
                $title .= ' that are ' . implode(', ', $filters);
            }
        }

        return $title;
    }

    protected function getMetaDescription(): string
    {
        if (false) {
            $description = "Viewing {$this->rater->id}'s game ratings";

            $description .= ". Total ratings: {$this->totalRatingsCount}, ";
            $description .= "Listed game ratings: {$this->visibleGamesRatingsCount}. ";

            return $description;
        }

        // For game list-like views
        if (property_exists($this, 'games')) {
            $description = 'Browse';

            $filters = [];

            // Build status filter
            if (property_exists($this, 'selectedStatuses') && ! empty($this->selectedStatuses)) {
                $statuses = array_map('strtolower', $this->selectedStatuses);
                $filters[] = implode(' and ', $statuses);
            }

            // Build engine filter
            if (property_exists($this, 'selectedEngines') && ! empty($this->selectedEngines)) {
                $filters[] = 'created with ' . implode(' and ', $this->selectedEngines);
            }

            // Add NSFW/SFW status
            if (property_exists($this, 'nsfw') && $this->nsfw) {
                $description .= ' NSFW';
            } elseif (property_exists($this, 'sfw') && $this->sfw) {
                $description .= ' SFW';
            }

            // Add platform information
            if (property_exists($this, 'selectedPlatforms') && ! empty($this->selectedPlatforms)) {
                $platforms = array_map('ucfirst', $this->selectedPlatforms);
                $description .= ' ' . implode('/', $platforms);
            }

            $description .= ' FVNs';

            // Add filters
            if (! empty($filters)) {
                $description .= ' that are ' . implode(' and ', $filters);
            }

            // Add language information
            if (property_exists($this, 'selectedLanguages') && ! empty($this->selectedLanguages)) {
                $languages = Language::whereIn('id', $this->selectedLanguages)
                    ->pluck('ref_name')
                    ->implode('/');
                $description .= " in {$languages}";
            }

            // Add search term
            if (property_exists($this, 'search') && $this->search) {
                $description .= " matching '{$this->search}'";
            }

            // Add game count
            $description .= ". Featuring {$this->games->total()} titles";

            // Add featured games
            $featuredGames = $this->games->take(4)->pluck('name')->implode(', ');
            if ($featuredGames) {
                $description .= ", including: {$featuredGames}";
            }

            // Add sort information if not default
            if (property_exists($this, 'sortField') && property_exists($this, 'sortDirection') &&
                ! $this->isDefaultSort($this->sortField, $this->sortDirection)) {
                $sortLabel = method_exists($this, 'getSortLabel') ?
                    $this->getSortLabel($this->sortField) :
                    ucfirst(str_replace('_', ' ', $this->sortField));

                $description .= ", sorted by {$sortLabel} " .
                    ($this->sortDirection === 'asc' ? 'ascending' : 'descending');
            }

            $description .= '.';

            return $description;
        }

        return (string) config('app.description', 'Default description');
    }

    protected function getMetaImage(): string
    {
        if (property_exists($this, 'games') && $this->games->count() > 0) {
            // Extract actual games from paginator if needed
            $actualGames = $this->games;
            if (method_exists($this->games, 'items')) {
                // This is a paginator, get the actual items
                $actualGames = collect($this->games->items());
            } elseif (isset($this->games->data)) {
                // This might be an array with data property
                $actualGames = collect($this->games->data);
            }

            // Generate a collage for GameList views with multiple games when:
            // 1. It's a social media crawler
            $shouldGenerateCollage = $this->isGameListView() &&
                $actualGames->count() > 1 &&
                $this->isSocialMediaCrawler();

            if ($shouldGenerateCollage) {
                // Log collage generation attempt for debugging
                logger()->info('Attempting to generate social media collage', [
                    'games_count' => $actualGames->count(),
                    'is_game_list_view' => $this->isGameListView(),
                    'is_social_crawler' => $this->isSocialMediaCrawler(),
                    'user_agent' => request()->header('User-Agent'),
                ]);

                $socialImageService = app(SocialImageService::class);

                // Generate cache key based on current filters and games
                $filters = $this->getCurrentFilters();
                $cacheKey = $socialImageService->generateCacheKey($actualGames, $filters);

                $collageUrl = $socialImageService->generateGameCollage($actualGames, $cacheKey);
                if ($collageUrl) {
                    // Log successful collage generation for debugging
                    logger()->info('Generated social media collage', [
                        'url' => $collageUrl,
                        'cache_key' => $cacheKey,
                        'games_count' => $actualGames->count(),
                        'is_social_crawler' => $this->isSocialMediaCrawler(),
                        'user_agent' => request()->header('User-Agent'),
                    ]);

                    return $collageUrl;
                }

                // If collage generation failed, use static fallback image
                $fallbackUrl = $socialImageService->getDefaultSocialImage();
                if ($fallbackUrl) {
                    return $fallbackUrl;
                }
            }

            // For non-social media requests with games, return first game's thumbnail
            if ($actualGames->count() > 0) {
                $game = $actualGames->first();
                if ($game) {
                    // Handle real Game models with getThumbnailUrl() method
                    if (method_exists($game, 'getThumbnailUrl')) {
                        $thumbnailUrl = $game->getThumbnailUrl();
                        if ($thumbnailUrl) {
                            return $thumbnailUrl;
                        }
                    }
                    // Handle test objects with thumb_url property
                    elseif (isset($game->thumb_url)) {
                        return $game->thumb_url;
                    }
                }
            }
        }

        // For no games or empty games, return empty string (not default image)
        if (property_exists($this, 'games') && $this->games->count() === 0) {
            return '';
        }

        // Final fallback to default social image for other cases
        $socialImageService = app(SocialImageService::class);

        return $socialImageService->getDefaultSocialImage();
    }

    /**
     * Check if this is a GameList view (where we show multiple games in a list)
     */
    private function isGameListView(): bool
    {
        // Consider presence of a paginator with multiple games as a list view
        return property_exists($this, 'games');
    }

    /**
     * Check if the current request is from a social media crawler
     */
    private function isSocialMediaCrawler(): bool
    {
        $userAgent = request()->header('User-Agent', '');

        $socialCrawlers = [
            'facebookexternalhit',     // Facebook
            'Facebot',                 // Facebook
            'facebookcatalog',         // Facebook Catalog
            'Twitterbot',              // Twitter/X
            'LinkedInBot',             // LinkedIn
            'WhatsApp',                // WhatsApp
            'Slackbot',                // Slack
            'SkypeUriPreview',         // Skype
            'TelegramBot',             // Telegram
            'Discordbot',              // Discord
            'redditbot',               // Reddit
            'Applebot',                // Apple (iMessage, etc.)
            'Mastodon',                // Mastodon
            'BlueSkyBot',              // BlueSky
            'Cardyb',                  // BlueSky (Cardyb crawler)
            'Pinterestbot',            // Pinterest
            'Snap URL Preview Service', // Snapchat
            'Viber',                   // Viber
            'OdklBot',                 // Odnoklassniki
            'ia_archiver',             // Internet Archive
        ];

        foreach ($socialCrawlers as $crawler) {
            if (stripos($userAgent, $crawler) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get current filters as an array for cache key generation
     */
    private function getCurrentFilters(): array
    {
        $filters = [];

        if (property_exists($this, 'search') && $this->search) {
            $filters['search'] = $this->search;
        }

        if (property_exists($this, 'selectedStatuses') && ! empty($this->selectedStatuses)) {
            $filters['statuses'] = $this->selectedStatuses;
        }

        if (property_exists($this, 'selectedEngines') && ! empty($this->selectedEngines)) {
            $filters['engines'] = $this->selectedEngines;
        }

        if (property_exists($this, 'selectedPlatforms') && ! empty($this->selectedPlatforms)) {
            $filters['platforms'] = $this->selectedPlatforms;
        }

        if (property_exists($this, 'selectedLanguages') && ! empty($this->selectedLanguages)) {
            $filters['languages'] = $this->selectedLanguages;
        }

        if (property_exists($this, 'selectedGameJams') && ! empty($this->selectedGameJams)) {
            $filters['gamejams'] = $this->selectedGameJams;
        }

        if (property_exists($this, 'selectedTags') && ! empty($this->selectedTags)) {
            $filters['tags'] = $this->selectedTags;
        }

        if (property_exists($this, 'nsfw') && $this->nsfw) {
            $filters['nsfw'] = true;
        }

        if (property_exists($this, 'sfw') && $this->sfw) {
            $filters['sfw'] = true;
        }

        if (property_exists($this, 'sortField') && $this->sortField) {
            $filters['sort'] = $this->sortField . '_' . ($this->sortDirection ?? 'desc');
        }

        return $filters;
    }

    /**
     * Check if there are any active filters on the current view
     */
    private function hasActiveFilters(): bool
    {
        // Check for various filter properties that indicate active filtering
        if (property_exists($this, 'selectedStatuses') && ! empty($this->selectedStatuses)) {
            return true;
        }

        if (property_exists($this, 'selectedEngines') && ! empty($this->selectedEngines)) {
            return true;
        }

        if (property_exists($this, 'selectedPlatforms') && ! empty($this->selectedPlatforms)) {
            return true;
        }

        if (property_exists($this, 'selectedLanguages') && ! empty($this->selectedLanguages)) {
            return true;
        }

        if (property_exists($this, 'selectedGameJams') && ! empty($this->selectedGameJams)) {
            return true;
        }

        if (property_exists($this, 'selectedTags') && ! empty($this->selectedTags)) {
            return true;
        }

        if (property_exists($this, 'search') && $this->search) {
            return true;
        }

        if (property_exists($this, 'nsfw') && $this->nsfw) {
            return true;
        }

        if (property_exists($this, 'sfw') && $this->sfw) {
            return true;
        }

        return false;
    }

    /**
     * Check if social preview should be generated based on query parameter
     */
    private function shouldGenerateSocialPreview(): bool
    {
        $socialPreview = request()->query('social_preview');

        return $socialPreview === '1' || $socialPreview === 'true';
    }
}
