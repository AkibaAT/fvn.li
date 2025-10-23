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
     * Execute the console command.
     */
    public function handle(SearchIndexService $searchService): int
    {
        $type = $this->option('type');

        $this->info('🔄 Starting Meilisearch maintenance reindex...');
        $this->line('ℹ️  Note: Normal operations use automatic indexing via Eloquent observers');

        // For specific content types, just use the full reindex
        // Individual type reindexing is rarely needed since observers handle updates automatically
        if ($type !== 'all') {
            $this->warn('⚠️  Specific type reindexing is rarely needed.');
            $this->line('Normal operations use automatic indexing via Eloquent observers.');
            $this->line('Consider using --type=all for full maintenance reindex.');

            if (! $this->confirm('Continue with full reindex instead?')) {
                return Command::SUCCESS;
            }
        }

        $this->info('🔄 Performing full maintenance reindex...');
        $stats = $searchService->fullReindex();

        $this->table(
            ['Content Type', 'Items Indexed'],
            [
                ['Games', $stats['games']],
                ['Dialogue Lines', $stats['dialogue_lines']],
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
}
