<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DialogueLine;
use App\Models\Game;
use App\Models\Rating;
use App\Models\Tag;
use Exception;
use Illuminate\Support\Facades\Log;

class SearchIndexService
{
    public function fullReindex(): array
    {
        $stats = [
            'games' => 0,
            'dialogue_texts' => 0,
            'reviews' => 0,
            'tags' => 0,
            'errors' => [],
        ];

        try {
            // Reindex games
            Game::where('is_visible', true)->chunk(100, function ($games) use (&$stats) {
                $games->searchable();
                $stats['games'] += $games->count();
            });

            // Reindex dialogue texts (per-game deduplication)
            // Get all games that have dialogue
            $gameIds = \DB::table('version_dialogue_lines as vdl')
                ->join('game_versions as gv', 'vdl.game_version_id', '=', 'gv.id')
                ->distinct()
                ->pluck('gv.game_id');

            foreach ($gameIds as $gameId) {
                try {
                    $dialogueTexts = \App\Models\GameDialogueText::getForGame($gameId);
                    if ($dialogueTexts->isNotEmpty()) {
                        $dialogueTexts->chunk(500)->each(function ($chunk) {
                            $chunk->searchable();
                        });
                        $stats['dialogue_texts'] += $dialogueTexts->count();
                    }
                } catch (Exception $e) {
                    $stats['errors'][] = "Game {$gameId}: {$e->getMessage()}";
                }
            }

            // Reindex reviews
            Rating::where('is_visible', true)
                ->where('is_reviewed', true)
                ->whereRaw("trim(review) != ''")
                ->chunk(100, function ($reviews) use (&$stats) {
                    $reviews->searchable();
                    $stats['reviews'] += $reviews->count();
                });

            // Reindex tags
            Tag::whereRaw("trim(name) != ''")->chunk(100, function ($tags) use (&$stats) {
                $tags->searchable();
                $stats['tags'] += $tags->count();
            });

            Log::info('Full search reindex completed', $stats);
        } catch (Exception $e) {
            $stats['errors'][] = $e->getMessage();
            Log::error('Full search reindex failed', [
                'error' => $e->getMessage(),
                'stats' => $stats,
            ]);
        }

        return $stats;
    }

    /**
     * Remove items from search index.
     */
    public function removeFromIndex(string $modelClass, array $ids): void
    {
        try {
            if (empty($ids)) {
                return;
            }

            // Create dummy models with the IDs to remove them from the index
            $models = collect($ids)->map(function ($id) use ($modelClass) {
                $model = new $modelClass;
                $model->id = $id;

                return $model;
            });

            // Remove from search index
            $models->unsearchable();

            Log::info('Removed items from search index', [
                'model' => $modelClass,
                'ids_count' => count($ids),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to remove items from search index', [
                'model' => $modelClass,
                'ids_count' => count($ids),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function getIndexStats(): array
    {
        try {
            $client = app(\Meilisearch\Client::class);

            $stats = [];
            $indexes = ['games', 'game_dialogue_texts', 'reviews', 'tags'];

            foreach ($indexes as $indexName) {
                try {
                    $index = $client->index($indexName);
                    $indexStats = $index->stats();
                    $stats[$indexName] = [
                        'numberOfDocuments' => $indexStats['numberOfDocuments'] ?? 0,
                        'isIndexing' => $indexStats['isIndexing'] ?? false,
                        'fieldDistribution' => $indexStats['fieldDistribution'] ?? [],
                    ];
                } catch (Exception $e) {
                    $stats[$indexName] = [
                        'error' => $e->getMessage(),
                        'numberOfDocuments' => 0,
                        'isIndexing' => false,
                    ];
                }
            }

            return $stats;
        } catch (Exception $e) {
            Log::error('Failed to get search index statistics', [
                'error' => $e->getMessage(),
            ]);

            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Check if search indexes are healthy.
     */
    public function healthCheck(): array
    {
        try {
            $client = app(\Meilisearch\Client::class);
            $health = $client->health();

            $stats = $this->getIndexStats();

            return [
                'meilisearch_status' => $health['status'] ?? 'unknown',
                'indexes' => $stats,
                'healthy' => ($health['status'] ?? '') === 'available',
            ];
        } catch (Exception $e) {
            return [
                'meilisearch_status' => 'error',
                'error' => $e->getMessage(),
                'healthy' => false,
            ];
        }
    }
}
