<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\GameDialogueText;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class IndexGameDialogueTexts extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'dialogue:index
                          {--game= : Index only a specific game ID}
                          {--fresh : Delete and recreate the index}';

    /**
     * The console command description.
     */
    protected $description = 'Index game dialogue texts to Meilisearch with per-game deduplication';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->info('Deleting existing index...');
            \Artisan::call('scout:delete-index', ['name' => 'game_dialogue_texts']);
            $this->info('Syncing index settings...');
            \Artisan::call('scout:sync-index-settings');
        }

        if ($gameId = $this->option('game')) {
            return $this->indexGame((int) $gameId);
        }

        return $this->indexAllGames();
    }

    /**
     * Index dialogue texts for a specific game.
     */
    protected function indexGame(int $gameId): int
    {
        $this->info("Indexing dialogue texts for game {$gameId}...");

        $dialogueTexts = GameDialogueText::getForGame($gameId);

        if ($dialogueTexts->isEmpty()) {
            $this->warn("No dialogue texts found for game {$gameId}");
            return 0;
        }

        $this->info("Found {$dialogueTexts->count()} unique texts");

        // Push to Meilisearch in chunks
        $indexed = 0;
        $dialogueTexts->chunk(500)->each(function ($chunk) use (&$indexed) {
            $chunk->searchable();
            $indexed += $chunk->count();
            $this->line("  Indexed {$indexed} texts...");
        });

        $this->info("✓ Successfully indexed {$indexed} texts for game {$gameId}");

        return 0;
    }

    /**
     * Index dialogue texts for all games.
     */
    protected function indexAllGames(): int
    {
        // Get all games that have dialogue
        $gameIds = DB::table('version_dialogue_lines as vdl')
            ->join('game_versions as gv', 'vdl.game_version_id', '=', 'gv.id')
            ->distinct()
            ->pluck('gv.game_id');

        $totalGames = $gameIds->count();
        $this->info("Found {$totalGames} games with dialogue to index");

        $bar = $this->output->createProgressBar($totalGames);
        $bar->start();

        $totalIndexed = 0;
        $errors = 0;

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
            } catch (\Exception $e) {
                $errors++;
                $this->newLine();
                $this->error("Failed to index game {$gameId}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✓ Indexing complete!");
        $this->info("  Total entries indexed: {$totalIndexed}");

        if ($errors > 0) {
            $this->warn("  Errors: {$errors} games failed to index");
            return 1;
        }

        return 0;
    }
}
