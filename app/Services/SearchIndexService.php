<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\GameDialogueText;
use App\Models\Rating;
use App\Models\Tag;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SearchIndexService
{
    public function fullReindex(?callable $progressCallback = null): array
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
            Game::where('is_visible', true)
                ->with(['tags', 'gameJams', 'gameVersions'])
                ->chunk(100, function ($games) use (&$stats, $progressCallback) {
                    $games->searchable();
                    $stats['games'] += $games->count();
                    if ($progressCallback) {
                        $progressCallback($games->count());
                    }
                });

            // Reindex dialogue texts (per-game deduplication)
            // Get all games that have dialogue
            $gameIds = DB::table('version_dialogue_lines as vdl')
                ->join('game_versions as gv', 'vdl.game_version_id', '=', 'gv.id')
                ->distinct()
                ->pluck('gv.game_id');

            foreach ($gameIds as $gameId) {
                try {
                    $dialogueTexts = GameDialogueText::getForGame($gameId);
                    if ($dialogueTexts->isNotEmpty()) {
                        $dialogueTexts->chunk(500)->each(function ($chunk) use ($progressCallback) {
                            $chunk->searchable();
                            if ($progressCallback) {
                                $progressCallback($chunk->count());
                            }
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
                ->chunk(100, function ($reviews) use (&$stats, $progressCallback) {
                    $reviews->searchable();
                    $stats['reviews'] += $reviews->count();
                    if ($progressCallback) {
                        $progressCallback($reviews->count());
                    }
                });

            // Reindex tags
            Tag::whereRaw("trim(name) != ''")->chunk(100, function ($tags) use (&$stats, $progressCallback) {
                $tags->searchable();
                $stats['tags'] += $tags->count();
                if ($progressCallback) {
                    $progressCallback($tags->count());
                }
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
     * Reindex only games.
     */
    public function reindexGames(?callable $progressCallback = null): array
    {
        $stats = ['count' => 0, 'errors' => []];

        try {
            Game::where('is_visible', true)
                ->with(['tags', 'gameJams', 'gameVersions'])
                ->chunk(100, function ($games) use (&$stats, $progressCallback) {
                    $games->searchable();
                    $stats['count'] += $games->count();
                    if ($progressCallback) {
                        $progressCallback($games->count());
                    }
                });

            Log::info('Games reindexed', $stats);
        } catch (Exception $e) {
            $stats['errors'][] = $e->getMessage();
            Log::error('Games reindex failed', [
                'error' => $e->getMessage(),
                'stats' => $stats,
            ]);
        }

        return $stats;
    }

    /**
     * Reindex only dialogue texts.
     */
    public function reindexDialogue(?callable $progressCallback = null): array
    {
        $stats = ['count' => 0, 'errors' => []];

        try {
            // Get all games that have dialogue
            $gameIds = DB::table('version_dialogue_lines as vdl')
                ->join('game_versions as gv', 'vdl.game_version_id', '=', 'gv.id')
                ->distinct()
                ->pluck('gv.game_id');

            foreach ($gameIds as $gameId) {
                try {
                    $dialogueTexts = GameDialogueText::getForGame($gameId);
                    if ($dialogueTexts->isNotEmpty()) {
                        $dialogueTexts->chunk(500)->each(function ($chunk) use ($progressCallback) {
                            $chunk->searchable();
                            if ($progressCallback) {
                                $progressCallback($chunk->count());
                            }
                        });
                        $stats['count'] += $dialogueTexts->count();
                    }
                } catch (Exception $e) {
                    $stats['errors'][] = "Game {$gameId}: {$e->getMessage()}";
                }
            }

            Log::info('Dialogue texts reindexed', $stats);
        } catch (Exception $e) {
            $stats['errors'][] = $e->getMessage();
            Log::error('Dialogue texts reindex failed', [
                'error' => $e->getMessage(),
                'stats' => $stats,
            ]);
        }

        return $stats;
    }

    /**
     * Reindex only reviews.
     */
    public function reindexReviews(?callable $progressCallback = null): array
    {
        $stats = ['count' => 0, 'errors' => []];

        try {
            Rating::where('is_visible', true)
                ->where('is_reviewed', true)
                ->whereRaw("trim(review) != ''")
                ->chunk(100, function ($reviews) use (&$stats, $progressCallback) {
                    $reviews->searchable();
                    $stats['count'] += $reviews->count();
                    if ($progressCallback) {
                        $progressCallback($reviews->count());
                    }
                });

            Log::info('Reviews reindexed', $stats);
        } catch (Exception $e) {
            $stats['errors'][] = $e->getMessage();
            Log::error('Reviews reindex failed', [
                'error' => $e->getMessage(),
                'stats' => $stats,
            ]);
        }

        return $stats;
    }

    /**
     * Reindex only tags.
     */
    public function reindexTags(?callable $progressCallback = null): array
    {
        $stats = ['count' => 0, 'errors' => []];

        try {
            Tag::whereRaw("trim(name) != ''")->chunk(100, function ($tags) use (&$stats, $progressCallback) {
                $tags->searchable();
                $stats['count'] += $tags->count();
                if ($progressCallback) {
                    $progressCallback($tags->count());
                }
            });

            Log::info('Tags reindexed', $stats);
        } catch (Exception $e) {
            $stats['errors'][] = $e->getMessage();
            Log::error('Tags reindex failed', [
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
