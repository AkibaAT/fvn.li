<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use Exception;
use Illuminate\Console\Command;
use Meilisearch\Client;

class MeilisearchDebug extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meilisearch:debug {--query=* : Test search query}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debug Meilisearch configuration and search results';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Meilisearch Debug Information');
        $this->newLine();

        // 1. Check connection
        $this->checkConnection();

        // 2. Check index stats
        $this->checkIndexStats();

        // 3. Check index settings
        $this->checkIndexSettings();

        // 4. Check sample documents
        $this->checkSampleDocuments();

        // 5. Test search
        $query = $this->option('query')[0] ?? '*';
        $this->testSearch($query);

        // 6. Compare with database
        $this->compareDatabaseCounts();

        return Command::SUCCESS;
    }

    private function checkConnection(): void
    {
        $this->info('1️⃣  Connection Check');
        try {
            $client = app(Client::class);
            $health = $client->health();
            $this->line("   Status: {$health['status']}");
            $this->info('   ✅ Connected');
        } catch (Exception $e) {
            $this->error("   ❌ Connection failed: {$e->getMessage()}");
        }
        $this->newLine();
    }

    private function checkIndexStats(): void
    {
        $this->info('2️⃣  Index Statistics');
        try {
            $client = app(Client::class);
            $index = $client->index('games');
            $stats = $index->stats();

            $this->line("   Total documents: {$stats['numberOfDocuments']}");
            $this->line('   Is indexing: ' . ($stats['isIndexing'] ? 'Yes' : 'No'));

            if ($stats['numberOfDocuments'] === 0) {
                $this->warn('   ⚠️  Index is empty!');
            } else {
                $this->info('   ✅ Index has documents');
            }
        } catch (Exception $e) {
            $this->error("   ❌ Failed to get stats: {$e->getMessage()}");
        }
        $this->newLine();
    }

    private function checkIndexSettings(): void
    {
        $this->info('3️⃣  Index Settings');
        try {
            $client = app(Client::class);
            $index = $client->index('games');
            $settings = $index->getSettings();

            $this->line('   Filterable Attributes:');
            foreach ($settings['filterableAttributes'] ?? [] as $attr) {
                $this->line("     • {$attr}");
            }

            $this->newLine();
            $this->line('   Sortable Attributes:');
            foreach ($settings['sortableAttributes'] ?? [] as $attr) {
                $this->line("     • {$attr}");
            }

            $this->newLine();
            $this->line('   Searchable Attributes:');
            foreach ($settings['searchableAttributes'] ?? [] as $attr) {
                $this->line("     • {$attr}");
            }

            $this->info('   ✅ Settings retrieved');
        } catch (Exception $e) {
            $this->error("   ❌ Failed to get settings: {$e->getMessage()}");
        }
        $this->newLine();
    }

    private function checkSampleDocuments(): void
    {
        $this->info('4️⃣  Sample Documents');
        try {
            $client = app(Client::class);
            $index = $client->index('games');

            // Get first 3 documents
            $results = $index->search('', ['limit' => 3]);
            $hits = $results->getHits();

            if (empty($hits)) {
                $this->warn('   ⚠️  No documents found in index');

                return;
            }

            foreach ($hits as $i => $doc) {
                $this->line('   Document ' . ($i + 1) . ':');
                $this->line("     ID: {$doc['id']}");
                $this->line("     Name: {$doc['name']}");
                $this->line('     Is Visible: ' . json_encode($doc['is_visible']));
                $this->line("     Status: {$doc['status']}");
                $this->line('     First Visible At: ' . ($doc['first_visible_at'] ?? 'null'));
                $this->newLine();
            }

            $this->info('   ✅ Sample documents retrieved');
        } catch (Exception $e) {
            $this->error("   ❌ Failed to get documents: {$e->getMessage()}");
        }
        $this->newLine();
    }

    private function testSearch(string $query): void
    {
        $this->info("5️⃣  Test Search (query: '{$query}')");

        try {
            // Test via Scout
            $this->line('   Via Laravel Scout:');
            $scoutResults = Game::search($query)->take(5)->get();
            $this->line("     Results: {$scoutResults->count()}");

            if ($scoutResults->count() > 0) {
                foreach ($scoutResults as $game) {
                    $this->line("     • {$game->name} (ID: {$game->id})");
                }
            }

            $this->newLine();

            // Test with is_visible filter
            $this->line('   With is_visible=true filter:');
            $filteredResults = Game::search($query)
                ->where('is_visible', true)
                ->take(5)
                ->get();
            $this->line("     Results: {$filteredResults->count()}");

            if ($filteredResults->count() > 0) {
                foreach ($filteredResults as $game) {
                    $this->line("     • {$game->name} (ID: {$game->id})");
                }
            } else {
                $this->warn('     ⚠️  No results with is_visible=true filter!');
            }

            $this->newLine();

            // Test direct Meilisearch API
            $this->line('   Direct Meilisearch API:');
            $client = app(Client::class);
            $index = $client->index('games');
            $directResults = $index->search($query, [
                'limit' => 5,
                'filter' => 'is_visible = true',
            ]);

            $this->line("     Total hits: {$directResults->getEstimatedTotalHits()}");
            $this->line('     Returned: ' . count($directResults->getHits()));

            $hits = $directResults->getHits();
            if (! empty($hits)) {
                foreach ($hits as $hit) {
                    $this->line("     • {$hit['name']} (ID: {$hit['id']})");
                }
            } else {
                $this->warn('     ⚠️  No results from direct API call!');
            }

            $this->info('   ✅ Search test completed');
        } catch (Exception $e) {
            $this->error("   ❌ Search failed: {$e->getMessage()}");
            $this->line("   Trace: {$e->getTraceAsString()}");
        }
        $this->newLine();
    }

    private function compareDatabaseCounts(): void
    {
        $this->info('6️⃣  Database vs Index Comparison');

        try {
            // Database counts
            $dbTotal = Game::count();
            $dbVisible = Game::where('is_visible', true)->count();

            $this->line('   Database:');
            $this->line("     Total games: {$dbTotal}");
            $this->line("     Visible games: {$dbVisible}");

            // Index counts
            $client = app(Client::class);
            $index = $client->index('games');
            $stats = $index->stats();
            $indexTotal = $stats['numberOfDocuments'];

            // Count visible in index
            $visibleInIndex = $index->search('', [
                'filter' => 'is_visible = true',
                'limit' => 0,
            ]);

            $this->newLine();
            $this->line('   Meilisearch Index:');
            $this->line("     Total documents: {$indexTotal}");
            $this->line("     Visible documents: {$visibleInIndex->getEstimatedTotalHits()}");

            $this->newLine();
            if ($dbVisible !== $visibleInIndex->getEstimatedTotalHits()) {
                $this->warn("   ⚠️  Mismatch! Database has {$dbVisible} visible games but index has {$visibleInIndex->getEstimatedTotalHits()}");
                $this->line('   Consider running: php artisan meilisearch:setup --force');
            } else {
                $this->info('   ✅ Counts match!');
            }
        } catch (Exception $e) {
            $this->error("   ❌ Comparison failed: {$e->getMessage()}");
        }
    }
}
