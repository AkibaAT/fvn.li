<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Livewire\GameList;
use App\Services\SocialImageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;

class PreGenerateSocialImages extends Command
{
    protected $signature = 'app:pregenerate-social-images {--popular : Only generate for popular filter combinations}';

    protected $description = 'Pre-generate social media images for common filter combinations';

    public function handle(SocialImageService $socialImageService): int
    {
        $this->info('Pre-generating social media images...');

        // Create a GameList component instance to get games
        $gameList = new GameList;

        // Initialize the component
        $gameList->mount();

        // Get the games without rendering the full view
        $reflection = new ReflectionMethod($gameList, 'render');
        $view = $reflection->invoke($gameList);
        $games = $view->getData()['games'];

        if ($games->isEmpty()) {
            $this->warn('No games found to generate images for.');

            return self::SUCCESS;
        }

        $filterCombinations = [];

        if ($this->option('popular')) {
            // Only generate for the most common combinations
            $filterCombinations = [
                // Default view (no filters)
                [],
                // NSFW filter
                ['nsfw' => true],
                // SFW filter
                ['sfw' => true],
                // Popular engines
                ['selectedEngines' => ['Ren\'Py']],
                ['selectedEngines' => ['Unity']],
                // Popular sort orders
                ['sortField' => 'rating', 'sortDirection' => 'desc'],
                ['sortField' => 'latest_version_published_at', 'sortDirection' => 'desc'],
                ['sortField' => 'trending', 'sortDirection' => 'desc'],
            ];
        } else {
            // Generate for all possible single-filter combinations
            $filterOptions = $gameList->getFilterOptions();

            // Add base combinations
            $filterCombinations[] = [];
            $filterCombinations[] = ['nsfw' => true];
            $filterCombinations[] = ['sfw' => true];

            // Add engine combinations
            foreach (array_keys($filterOptions['gameEngines']) as $engine) {
                $filterCombinations[] = ['selectedEngines' => [$engine]];
            }

            // Add platform combinations
            foreach (array_keys($filterOptions['platforms']) as $platform) {
                $filterCombinations[] = ['selectedPlatforms' => [$platform]];
            }

            // Add popular sort combinations
            $sortFields = ['rating', 'latest_version_published_at', 'trending', 'rating_count'];
            foreach ($sortFields as $field) {
                $filterCombinations[] = ['sortField' => $field, 'sortDirection' => 'desc'];
            }
        }

        $generated = 0;
        $cached = 0;

        foreach ($filterCombinations as $filters) {
            // Apply filters to get a different set of games if needed
            $cacheKey = $socialImageService->generateCacheKey($games, $filters);

            // Check if already cached
            if (Cache::has("social_image_{$cacheKey}")) {
                $cached++;
                $this->line('Cached: ' . $this->describeFilters($filters));

                continue;
            }

            $this->line('Generating: ' . $this->describeFilters($filters));

            $imageUrl = $socialImageService->generateGameCollage($games, $cacheKey);

            if ($imageUrl) {
                $generated++;
                $this->info("✓ Generated: {$imageUrl}");
            } else {
                $this->warn('✗ Failed to generate image for: ' . $this->describeFilters($filters));
            }
        }

        $this->info('Pre-generation completed!');
        $this->info("Generated: {$generated} new WebP images");
        $this->info("Already cached: {$cached} images");

        return self::SUCCESS;
    }

    private function describeFilters(array $filters): string
    {
        if (empty($filters)) {
            return 'Default view (no filters)';
        }

        $descriptions = [];

        foreach ($filters as $key => $value) {
            match ($key) {
                'nsfw' => $descriptions[] = 'NSFW',
                'sfw' => $descriptions[] = 'SFW',
                'selectedEngines' => $descriptions[] = 'Engine: ' . implode(', ', $value),
                'selectedPlatforms' => $descriptions[] = 'Platform: ' . implode(', ', $value),
                'sortField' => $descriptions[] = 'Sort: ' . $value . ' ' . ($filters['sortDirection'] ?? 'desc'),
                default => $descriptions[] = "{$key}: " . (is_array($value) ? implode(', ', $value) : $value)
            };
        }

        return implode(', ', $descriptions);
    }
}
