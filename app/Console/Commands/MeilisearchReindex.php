<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SearchIndexService;
use Illuminate\Console\Command;

class MeilisearchReindex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meilisearch:reindex
        {--type=all : Type of content to reindex (all, games, dialogue, reviews, tags)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reindex content in Meilisearch for maintenance (normal operations use automatic indexing)';

    /**
     * Handle full reindex of all content types.
     */
    protected function handleFullReindex(SearchIndexService $searchService): int
    {
        $this->info('🔄 Performing full maintenance reindex...');
        $stats = $searchService->fullReindex(function ($count) {
            if (! isset($this->bar)) {
                $this->bar = $this->output->createProgressBar($count);
                $this->bar->start();
            } else {
                $this->bar->advance($count);
            }
        });

        if (isset($this->bar)) {
            $this->bar->finish();
            $this->newLine();
            unset($this->bar);
        }

        $this->table(
            ['Content Type', 'Items Indexed'],
            [
                ['Games', $stats['games']],
                ['Dialogue Texts', $stats['dialogue_texts']],
                ['Reviews', $stats['reviews']],
                ['Tags', $stats['tags']],
            ]
        );

        if (! empty($stats['errors'])) {
            $this->error('❌ Errors occurred during reindexing:');
            foreach ($stats['errors'] as $error) {
                $this->line("  • {$error}");
            }

            return Command::FAILURE;
        }

        $this->info('🎉 Maintenance reindex completed successfully!');

        return Command::SUCCESS;
    }

    /**
     * Handle reindexing of a specific content type.
     */
    protected function handleTypeReindex(string $type, SearchIndexService $searchService): int
    {
        $this->info("🔄 Reindexing {$type}...");

        // Create progress callback
        $progressCallback = function ($count) {
            if (! isset($this->bar)) {
                $this->bar = $this->output->createProgressBar($count);
                $this->bar->start();
            } else {
                $this->bar->advance($count);
            }
        };

        $stats = match ($type) {
            'games' => $searchService->reindexGames($progressCallback),
            'dialogue' => $searchService->reindexDialogue($progressCallback),
            'reviews' => $searchService->reindexReviews($progressCallback),
            'tags' => $searchService->reindexTags($progressCallback),
            default => throw new \InvalidArgumentException("Invalid type: {$type}"),
        };

        if (isset($this->bar)) {
            $this->bar->finish();
            $this->newLine();
            unset($this->bar);
        }

        $this->info("✅ Reindexed {$stats['count']} {$type}");

        if (! empty($stats['errors'])) {
            $this->error('❌ Errors occurred during reindexing:');
            foreach ($stats['errors'] as $error) {
                $this->line("  • {$error}");
            }

            return Command::FAILURE;
        }

        $this->info('🎉 Reindex completed successfully!');

        return Command::SUCCESS;
    }

    /**
     * Execute the console command.
     */
    public function handle(SearchIndexService $searchService): int
    {
        $type = $this->option('type');

        $this->info('🔄 Starting Meilisearch maintenance reindex...');
        $this->line('ℹ️  Note: Normal operations use automatic indexing via Eloquent observers');

        if ($type === 'all') {
            return $this->handleFullReindex($searchService);
        }

        // Handle specific type reindexing with progress bar
        return $this->handleTypeReindex($type, $searchService);
    }
}
