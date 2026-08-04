<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\UniqueDialogueText;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Meilisearch\Client;

class IndexDialogueTexts extends Command
{
    protected $signature = 'dialogue:index-texts {--batch-size=1000 : Number of texts to process per batch}';

    protected $description = 'Efficiently index unique dialogue texts to Meilisearch with pre-aggregated metadata';

    public function handle(): int
    {
        $this->info('  Indexing unique dialogue texts to Meilisearch...');

        $batchSize = (int) $this->option('batch-size');
        $totalTexts = UniqueDialogueText::whereRaw("trim(text_content) != ''")->count();

        $this->line("  Total unique texts to index: {$totalTexts}");
        $this->line("  Batch size: {$batchSize}");

        $bar = $this->output->createProgressBar($totalTexts);
        $bar->start();

        $client = app(Client::class);
        $index = $client->index('dialogue_texts');

        $processed = 0;
        $errors = [];

        // Process in batches
        UniqueDialogueText::whereRaw("trim(text_content) != ''")
            ->orderBy('id')
            ->chunk($batchSize, function ($texts) use ($bar, &$processed, &$errors, $index) {
                try {
                    // Pre-aggregate all metadata for this batch in a single query
                    $textIds = $texts->pluck('id')->toArray();
                    $metadata = $this->aggregateMetadata($textIds);

                    // Build documents for Meilisearch
                    $documents = [];
                    foreach ($texts as $text) {
                        $meta = $metadata[$text->id] ?? null;
                        if (! $meta) {
                            continue; // Skip texts with no dialogue lines
                        }

                        $documents[] = [
                            'id' => $text->id,
                            'text_content' => $text->text_content,
                            'game_ids' => $meta['game_ids'],
                            'game_names' => $meta['game_names'],
                            'version_ids' => $meta['version_ids'],
                            'character_ids' => $meta['character_ids'],
                            'character_names' => $meta['character_names'],
                            'languages' => $meta['languages'],
                            'usage_count' => $meta['usage_count'],
                            'games_count' => $meta['games_count'],
                        ];
                    }

                    // Send to Meilisearch
                    if (! empty($documents)) {
                        $index->addDocuments($documents);
                    }

                    $processed += $texts->count();
                    $bar->advance($texts->count());
                } catch (Exception $e) {
                    $errors[] = "Batch error: {$e->getMessage()}";
                    $bar->advance($texts->count());
                }
            });

        $bar->finish();
        $this->newLine();

        if (! empty($errors)) {
            $this->error('Errors occurred during indexing:');
            foreach ($errors as $error) {
                $this->line("  • {$error}");
            }

            return Command::FAILURE;
        }

        $this->info("Successfully indexed {$processed} unique dialogue texts!");

        return Command::SUCCESS;
    }

    /**
     * Aggregate metadata for a batch of text IDs in a single efficient query.
     */
    private function aggregateMetadata(array $textIds): array
    {
        // Get aggregated data for games, versions, languages
        $aggregated = DB::select('
            SELECT
                vdl.text_id,
                COUNT(DISTINCT gv.game_id) as games_count,
                COUNT(vdl.id) as usage_count,
                ARRAY_AGG(DISTINCT gv.game_id) as game_ids,
                ARRAY_AGG(DISTINCT g.name) as game_names,
                ARRAY_AGG(DISTINCT vdl.game_version_id) as version_ids,
                ARRAY_AGG(DISTINCT vdl.character_id) as character_ids,
                ARRAY_AGG(DISTINCT vdl.iso_code) as languages
            FROM version_dialogue_lines vdl
            JOIN game_versions gv ON vdl.game_version_id = gv.id
            JOIN games g ON gv.game_id = g.id
            WHERE vdl.text_id = ANY(?)
            GROUP BY vdl.text_id
        ', ['{' . implode(',', $textIds) . '}']);

        // Get character names for all texts in batch
        $characterNamesByText = DB::table('version_dialogue_lines as vdl')
            ->join('characters as c', 'vdl.character_id', '=', 'c.id')
            ->whereIn('vdl.text_id', $textIds)
            ->select('vdl.text_id', 'c.display_names')
            ->get()
            ->groupBy('text_id')
            ->map(function ($group) {
                return $group->pluck('display_names')
                    ->flatMap(function ($displayNames) {
                        if (is_string($displayNames)) {
                            $displayNames = json_decode($displayNames, true);
                        }
                        if (is_array($displayNames) && ! empty($displayNames)) {
                            return [reset($displayNames)];
                        }

                        return [];
                    })
                    ->unique()
                    ->values()
                    ->toArray();
            })
            ->toArray();

        // Build result array
        $result = [];
        foreach ($aggregated as $row) {
            $result[$row->text_id] = [
                'game_ids' => $this->parseIntArray($row->game_ids),
                'game_names' => $this->parseStringArray($row->game_names),
                'version_ids' => $this->parseIntArray($row->version_ids),
                'character_ids' => $this->parseIntArray($row->character_ids),
                'character_names' => $characterNamesByText[$row->text_id] ?? [],
                'languages' => $this->parseStringArray($row->languages),
                'usage_count' => (int) $row->usage_count,
                'games_count' => (int) $row->games_count,
            ];
        }

        return $result;
    }

    /**
     * Parse PostgreSQL integer array to PHP array.
     */
    private function parseIntArray(?string $pgArray): array
    {
        if (empty($pgArray) || $pgArray === '{}') {
            return [];
        }
        $cleaned = trim($pgArray, '{}');
        if (empty($cleaned)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', explode(',', $cleaned))));
    }

    /**
     * Parse PostgreSQL string array to PHP array.
     */
    private function parseStringArray(?string $pgArray): array
    {
        if (empty($pgArray) || $pgArray === '{}') {
            return [];
        }
        $cleaned = trim($pgArray, '{}');
        if (empty($cleaned)) {
            return [];
        }
        // Handle quoted strings in PostgreSQL array format
        $parts = [];
        $current = '';
        $inQuotes = false;

        for ($i = 0; $i < strlen($cleaned); $i++) {
            $char = $cleaned[$i];

            if ($char === '"') {
                $inQuotes = ! $inQuotes;
            } elseif ($char === ',' && ! $inQuotes) {
                if ($current !== '') {
                    $parts[] = $current;
                    $current = '';
                }
            } else {
                $current .= $char;
            }
        }

        if ($current !== '') {
            $parts[] = $current;
        }

        return array_values(array_unique($parts));
    }
}
