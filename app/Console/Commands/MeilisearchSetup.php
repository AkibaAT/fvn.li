<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\GameDialogueText;
use App\Models\Tag;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;

class MeilisearchSetup extends Command
{
    private const SEARCH_VERIFICATION_ATTEMPTS = 10;

    private const SEARCH_VERIFICATION_RETRY_DELAY_MICROSECONDS = 250_000;

    private const SETTINGS_TASK_TIMEOUT_SECONDS = 600;

    private const SETTINGS_TASK_POLL_SECONDS = 2;

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
     * Verify that the setup actually worked by testing a search.
     */
    protected function verifySetup(): bool
    {
        try {
            $this->info('🔍 Verifying setup...');

            $gameCount = $this->visibleGameCount();
            if ($gameCount === 0) {
                $this->info('    ✅ Search verification successful');

                return true;
            }

            // Meilisearch document updates are asynchronous. Give submitted Scout
            // tasks a short window to become searchable before failing setup.
            for ($attempt = 1; $attempt <= self::SEARCH_VERIFICATION_ATTEMPTS; $attempt++) {
                if ($this->hasSearchableGameResult()) {
                    $this->info('    ✅ Search verification successful');

                    return true;
                }

                if ($attempt < self::SEARCH_VERIFICATION_ATTEMPTS) {
                    $this->line('    Waiting for indexed games to become searchable...');
                    $this->sleepBeforeSearchRetry();
                }
            }

            $this->warn("    ⚠️  Search returned no results but database has {$gameCount} visible game(s). Index may still be processing.");

            return false;
        } catch (Exception $e) {
            $this->error("    ❌ Verification failed: {$e->getMessage()}");

            return false;
        }
    }

    protected function visibleGameCount(): int
    {
        return Game::where('is_visible', true)->count();
    }

    protected function hasSearchableGameResult(): bool
    {
        return ! Game::search('*')->take(1)->get()->isEmpty();
    }

    protected function sleepBeforeSearchRetry(): void
    {
        usleep(self::SEARCH_VERIFICATION_RETRY_DELAY_MICROSECONDS);
    }

    /**
     * Check if Meilisearch is accessible.
     */
    private function checkMeilisearchConnection(): bool
    {
        try {
            // Try to get health status (this endpoint doesn't require authentication)
            $client = app(Client::class);
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
            $client = app(Client::class);
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
            $taskWatermark = $this->latestTaskUid();
            $exitCode = Artisan::call('scout:sync-index-settings');

            if ($exitCode !== 0) {
                $this->error('    ❌ Failed to sync index settings');

                return false;
            }

            if (! $this->awaitSettingsTasks($taskWatermark)) {
                return false;
            }

            $this->info('    ✅ All indexes configured');

            return $this->applyEmbedders();
        } catch (Exception $e) {
            $this->error('    ❌ Error setting up indexes: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Bring the configured embedders up to date, on their own tasks.
     */
    private function applyEmbedders(): bool
    {
        $this->line('  - Ensuring index embedders...');

        return Artisan::call('meilisearch:embedders', [], $this->output) === self::SUCCESS;
    }

    /**
     * The uid of the newest task Meilisearch knows about, used as the cutoff for
     * attributing settings tasks to this run. -1 when the queue is empty.
     */
    private function latestTaskUid(): int
    {
        $tasks = app(Client::class)
            ->getTasks((new TasksQuery)->setLimit(1))
            ->getResults();

        return isset($tasks[0]['uid']) ? (int) $tasks[0]['uid'] : -1;
    }

    /**
     * Wait for the settings tasks this run submitted and report any that failed.
     *
     * Meilisearch applies a settings update as one atomic asynchronous task, and
     * scout:sync-index-settings returns as soon as the task is enqueued. A task
     * that fails server-side leaves the index with none of its settings, so the
     * queue is the only place the outcome can be read.
     */
    private function awaitSettingsTasks(int $taskWatermark): bool
    {
        $client = app(Client::class);
        $deadline = time() + self::SETTINGS_TASK_TIMEOUT_SECONDS;
        $announcedWait = false;

        while (true) {
            $pending = $client->getTasks(
                (new TasksQuery)
                    ->setTypes(['settingsUpdate'])
                    ->setStatuses(['enqueued', 'processing'])
            )->getResults();

            if ($pending === []) {
                break;
            }

            if (time() >= $deadline) {
                $this->error('    ❌ Timed out waiting for index settings to be applied');
                $this->line('       Settings that configure an embedder download the model on first use.');

                return false;
            }

            if (! $announcedWait) {
                $this->line('    Waiting for index settings to be applied...');
                $announcedWait = true;
            }

            sleep(self::SETTINGS_TASK_POLL_SECONDS);
        }

        $failed = collect($client->getTasks(
            (new TasksQuery)
                ->setTypes(['settingsUpdate'])
                ->setStatuses(['failed'])
        )->getResults())
            ->filter(fn (array $task): bool => (int) ($task['uid'] ?? -1) > $taskWatermark);

        if ($failed->isEmpty()) {
            return true;
        }

        $this->error('    ❌ Meilisearch rejected the index settings; the indexes are unconfigured');
        foreach ($failed as $task) {
            $index = $task['indexUid'] ?? 'unknown';
            $message = $task['error']['message'] ?? 'no error message reported';
            $this->line("       • {$index}: {$message}");
        }

        return false;
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
                ->orderBy('id')
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

            GameDialogueText::deleteAllSearchDocuments();

            $bar = $this->output->createProgressBar($totalGames);
            $bar->start();

            $totalIndexed = 0;
            $errors = [];

            foreach ($gameIds as $gameId) {
                try {
                    $totalIndexed += GameDialogueText::indexSearchDocumentsForGame((int) $gameId);
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

            // Import tags
            $tagCount = Tag::whereRaw("trim(name) != ''")->count();
            $this->line("  - Importing {$tagCount} tags...");

            $bar = $this->output->createProgressBar($tagCount);
            $bar->start();

            $errors = [];
            Tag::whereRaw("trim(name) != ''")
                ->orderBy('id')
                ->chunk(100, function ($tags) use ($bar, &$errors) {
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
}
