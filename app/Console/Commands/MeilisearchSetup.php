<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\GameDialogueText;
use App\Models\Rating;
use App\Models\Tag;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

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

        $this->info('✅ Meilisearch connection successful (health endpoint)');

        // Verify authentication works
        if (! $this->checkAuthentication()) {
            $this->error('❌ Meilisearch authentication failed. Please check your MEILISEARCH_KEY.');
            $this->newLine();
            $this->warn('💡 Common issues:');
            $this->line('  • MEILISEARCH_KEY not set in .env');
            $this->line('  • MEILISEARCH_KEY does not match Meilisearch master key');
            $this->line('  • Need to run: php artisan config:clear && php artisan config:cache');

            return Command::FAILURE;
        }

        $this->info('✅ Meilisearch authentication successful');

        // Setup indexes
        if (! $this->setupIndexes()) {
            return Command::FAILURE;
        }

        // Import data
        if (! $this->importData()) {
            return Command::FAILURE;
        }

        // Verify the setup actually worked
        if (! $this->verifySetup()) {
            $this->error('❌ Setup verification failed. Data may not have been indexed correctly.');

            return Command::FAILURE;
        }

        $this->info('🎉 Meilisearch setup completed successfully!');
        $this->newLine();
        $this->info('✨ Search indexing is now automatic!');
        $this->line('  • New games and dialogue lines are indexed automatically');
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
            // Try to get health status (this endpoint doesn't require authentication)
            $client = app(\Meilisearch\Client::class);
            $health = $client->health();

            return $health['status'] === 'available';
        } catch (Exception $e) {
            $this->error("Connection error: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Check if authentication is working by trying an authenticated endpoint.
     */
    private function checkAuthentication(): bool
    {
        try {
            $client = app(\Meilisearch\Client::class);
            // Try to get indexes - this requires authentication
            $client->getIndexes();

            return true;
        } catch (Exception $e) {
            $this->error("Authentication error: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Set up Meilisearch indexes with proper settings.
     */
    private function setupIndexes(): bool
    {
        $this->info('📋 Setting up indexes...');

        try {
            // Sync all index settings at once
            $this->line('  - Syncing all index settings...');
            $exitCode = Artisan::call('scout:sync-index-settings');

            if ($exitCode !== 0) {
                $this->error('    ❌ Failed to sync index settings');

                return false;
            }

            $this->info('    ✅ All indexes configured');

            return true;
        } catch (Exception $e) {
            $this->error('    ❌ Error setting up indexes: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Import existing data to Meilisearch.
     */
    private function importData(): bool
    {
        $this->info('📦 Importing existing data...');

        try {
            // Import games
            $gameCount = Game::where('is_visible', true)->count();
            $this->line("  - Importing {$gameCount} games...");

            $bar = $this->output->createProgressBar($gameCount);
            $bar->start();

            $errors = [];
            Game::where('is_visible', true)
                ->with(['tags', 'gameJams', 'gameVersions'])
                ->chunk(100, function ($games) use ($bar, &$errors) {
                    try {
                        $games->searchable();
                        $bar->advance($games->count());
                    } catch (Exception $e) {
                        $errors[] = "Games chunk error: {$e->getMessage()}";
                        $bar->advance($games->count());
                    }
                });

            $bar->finish();
            $this->newLine();

            if (! empty($errors)) {
                $this->error('    ❌ Errors importing games:');
                foreach ($errors as $error) {
                    $this->line("      • {$error}");
                }

                return false;
            }

            $this->info('    ✅ Games imported');

            // Import dialogue texts (deduplicated per game)
            // Get all games that have dialogue
            $gameIds = DB::table('version_dialogue_lines as vdl')
                ->join('game_versions as gv', 'vdl.game_version_id', '=', 'gv.id')
                ->distinct()
                ->pluck('gv.game_id');

            $totalGames = $gameIds->count();
            $this->line("  - Importing dialogue texts from {$totalGames} games (per-game deduplication)...");

            $bar = $this->output->createProgressBar($totalGames);
            $bar->start();

            $totalIndexed = 0;
            $errors = [];

            foreach ($gameIds as $gameId) {
                try {
                    $dialogueTexts = GameDialogueText::getForGame($gameId);

                    if ($dialogueTexts->isNotEmpty()) {
                        // Push to Meilisearch in chunks
                        $dialogueTexts->chunk(500)->each(function ($chunk) {
                            $chunk->searchable();
                        });

                        $totalIndexed += $dialogueTexts->count();
                    }
                } catch (Exception $e) {
                    $errors[] = "Game {$gameId}: {$e->getMessage()}";
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();

            if (! empty($errors)) {
                $this->warn('    ⚠️  Some errors occurred:');
                foreach (array_slice($errors, 0, 5) as $error) {
                    $this->line("      • {$error}");
                }
                if (count($errors) > 5) {
                    $this->line('      • ... and ' . (count($errors) - 5) . ' more');
                }
            }

            $this->info("    ✅ Dialogue texts imported ({$totalIndexed} entries)");

            // Import reviews
            $reviewCount = Rating::where('is_visible', true)
                ->where('is_reviewed', true)
                ->whereRaw("trim(review) != ''")
                ->count();

            if ($reviewCount > 0) {
                $this->line("  - Importing {$reviewCount} reviews...");

                $bar = $this->output->createProgressBar($reviewCount);
                $bar->start();

                $errors = [];
                Rating::where('is_visible', true)
                    ->where('is_reviewed', true)
                    ->whereRaw("trim(review) != ''")
                    ->chunk(100, function ($reviews) use ($bar, &$errors) {
                        try {
                            $reviews->searchable();
                            $bar->advance($reviews->count());
                        } catch (Exception $e) {
                            $errors[] = "Reviews chunk error: {$e->getMessage()}";
                            $bar->advance($reviews->count());
                        }
                    });

                $bar->finish();
                $this->newLine();

                if (! empty($errors)) {
                    $this->error('    ❌ Errors importing reviews:');
                    foreach ($errors as $error) {
                        $this->line("      • {$error}");
                    }

                    return false;
                }

                $this->info('    ✅ Reviews imported');
            } else {
                $this->info('    ℹ️ No reviews to import');
            }

            // Import tags
            $tagCount = Tag::whereRaw("trim(name) != ''")->count();
            $this->line("  - Importing {$tagCount} tags...");

            $bar = $this->output->createProgressBar($tagCount);
            $bar->start();

            $errors = [];
            Tag::whereRaw("trim(name) != ''")->chunk(100, function ($tags) use ($bar, &$errors) {
                try {
                    $tags->searchable();
                    $bar->advance($tags->count());
                } catch (Exception $e) {
                    $errors[] = "Tags chunk error: {$e->getMessage()}";
                    $bar->advance($tags->count());
                }
            });

            $bar->finish();
            $this->newLine();

            if (! empty($errors)) {
                $this->error('    ❌ Errors importing tags:');
                foreach ($errors as $error) {
                    $this->line("      • {$error}");
                }

                return false;
            }

            $this->info('    ✅ Tags imported');

            return true;
        } catch (Exception $e) {
            $this->error("❌ Fatal error during import: {$e->getMessage()}");
            $this->line("Stack trace: {$e->getTraceAsString()}");

            return false;
        }
    }

    /**
     * Verify that the setup actually worked by testing a search.
     */
    private function verifySetup(): bool
    {
        try {
            $this->info('🔍 Verifying setup...');

            // Try to search for games
            $testResults = Game::search('*')->take(1)->get();

            if ($testResults->isEmpty()) {
                $gameCount = Game::where('is_visible', true)->count();
                if ($gameCount > 0) {
                    $this->warn('    ⚠️  Search returned no results but database has games. Index may be empty.');

                    return false;
                }
            }

            $this->info('    ✅ Search verification successful');

            return true;
        } catch (Exception $e) {
            $this->error("    ❌ Verification failed: {$e->getMessage()}");

            return false;
        }
    }
}
