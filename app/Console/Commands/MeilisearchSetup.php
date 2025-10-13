<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\Rating;
use App\Models\Tag;
use App\Models\UniqueDialogueText;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class MeilisearchSetup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meilisearch:setup {--force : Force reindexing even if indexes exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set up Meilisearch indexes and migrate data from PostgreSQL search';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Setting up Meilisearch for FVN.li...');

        // Check if Meilisearch is accessible
        if (! $this->checkMeilisearchConnection()) {
            $this->error('❌ Cannot connect to Meilisearch. Please ensure it is running.');

            return Command::FAILURE;
        }

        $this->info('✅ Meilisearch connection successful');

        // Setup indexes
        $this->setupIndexes();

        // Import data
        $this->importData();

        $this->info('🎉 Meilisearch setup completed successfully!');
        $this->newLine();
        $this->info('✨ Search indexing is now automatic!');
        $this->line('  • New games and dialogue texts are indexed automatically');
        $this->line('  • Updates to existing content trigger re-indexing');
        $this->line('  • No manual intervention needed for normal operations');
        $this->newLine();
        $this->info('💡 Useful commands:');
        $this->line('  • Test search: php artisan meilisearch:test "your query"');
        $this->line('  • Maintenance reindex: php artisan meilisearch:reindex');
        $this->line('  • Check search health: Use SearchIndexService::healthCheck()');
        $this->newLine();
        $this->info('🔧 For development testing:');
        $this->line('  php artisan tinker');
        $this->line('  >>> App\\Models\\Game::search("your query")->get()');

        return Command::SUCCESS;
    }

    /**
     * Check if Meilisearch is accessible.
     */
    private function checkMeilisearchConnection(): bool
    {
        try {
            // Try to get health status
            $client = app(\Meilisearch\Client::class);
            $health = $client->health();

            return $health['status'] === 'available';
        } catch (Exception $e) {
            $this->error("Connection error: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Set up Meilisearch indexes with proper settings.
     */
    private function setupIndexes(): void
    {
        $this->info('📋 Setting up indexes...');

        // Sync all index settings at once
        $this->line('  - Syncing all index settings...');
        Artisan::call('scout:sync-index-settings');
        $this->info('    ✅ All indexes configured');
    }

    /**
     * Import existing data to Meilisearch.
     */
    private function importData(): void
    {
        $this->info('📦 Importing existing data...');

        // Import games
        $gameCount = Game::where('is_visible', true)->count();
        $this->line("  - Importing {$gameCount} games...");

        $bar = $this->output->createProgressBar($gameCount);
        $bar->start();

        Game::where('is_visible', true)->chunk(100, function ($games) use ($bar) {
            $games->searchable();
            $bar->advance($games->count());
        });

        $bar->finish();
        $this->newLine();
        $this->info('    ✅ Games imported');

        // Import dialogue texts
        $dialogueCount = UniqueDialogueText::whereRaw("trim(text_content) != ''")->count();
        $this->line("  - Importing {$dialogueCount} dialogue texts...");

        $bar = $this->output->createProgressBar($dialogueCount);
        $bar->start();

        // Eager load relationships to avoid N+1 queries during indexing
        UniqueDialogueText::whereRaw("trim(text_content) != ''")
            ->with(['dialogueLines.character', 'dialogueLines.gameVersion.game'])
            ->chunk(500, function ($texts) use ($bar) {
                $texts->searchable();
                $bar->advance($texts->count());
            });

        $bar->finish();
        $this->newLine();
        $this->info('    ✅ Dialogue texts imported');

        // Import reviews
        $reviewCount = Rating::where('is_visible', true)
            ->where('is_reviewed', true)
            ->whereRaw("trim(review) != ''")
            ->count();

        if ($reviewCount > 0) {
            $this->line("  - Importing {$reviewCount} reviews...");

            $bar = $this->output->createProgressBar($reviewCount);
            $bar->start();

            Rating::where('is_visible', true)
                ->where('is_reviewed', true)
                ->whereRaw("trim(review) != ''")
                ->chunk(100, function ($reviews) use ($bar) {
                    $reviews->searchable();
                    $bar->advance($reviews->count());
                });

            $bar->finish();
            $this->newLine();
            $this->info('    ✅ Reviews imported');
        } else {
            $this->info('    ℹ️ No reviews to import');
        }

        // Import tags
        $tagCount = Tag::whereRaw("trim(name) != ''")->count();
        $this->line("  - Importing {$tagCount} tags...");

        $bar = $this->output->createProgressBar($tagCount);
        $bar->start();

        Tag::whereRaw("trim(name) != ''")->chunk(100, function ($tags) use ($bar) {
            $tags->searchable();
            $bar->advance($tags->count());
        });

        $bar->finish();
        $this->newLine();
        $this->info('    ✅ Tags imported');
    }
}
