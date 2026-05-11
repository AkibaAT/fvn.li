<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Searchable;

/**
 * GameDialogueText represents unique dialogue texts aggregated per game.
 * This is a virtual model (no database table) used solely for Meilisearch indexing.
 * Each record represents one unique text within one game, with arrays of version_ids
 * and character_ids where that text appears.
 */
class GameDialogueText extends Model
{
    use Searchable;

    /**
     * This model doesn't use a database table - it's a view model for indexing.
     */
    public $table = null;

    /**
     * Disable timestamps since this is a virtual model.
     */
    public $timestamps = false;

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'version_ids' => 'array',
        'character_ids' => 'array',
        'character_names' => 'array',
    ];

    /**
     * Generate all searchable records by aggregating dialogue texts per game.
     * This is called by Scout when running scout:import.
     *
     * We can't use the standard query builder approach since this is a virtual model.
     * Instead, we'll return a generator that yields chunks of models.
     */
    public static function makeAllSearchableUsing($query)
    {
        // Get all games that have dialogue
        $gameIds = DB::table('version_dialogue_lines as vdl')
            ->join('game_versions as gv', 'vdl.game_version_id', '=', 'gv.id')
            ->distinct()
            ->pluck('gv.game_id');

        echo 'Found '.$gameIds->count()." games with dialogue to index\n";

        // Process each game's dialogue texts
        return $gameIds->map(function ($gameId) {
            echo "  Processing game ID: {$gameId}\n";

            return static::getForGame($gameId);
        })->flatten();
    }

    /**
     * Get all game dialogue texts aggregated from the database.
     * Returns a collection of GameDialogueText instances.
     */
    public static function getAllGameDialogueTexts(): Collection
    {
        // Query to aggregate dialogue texts per game
        $results = DB::select('
            SELECT
                udt.id as text_id,
                gv.game_id,
                udt.text_content,
                vdl.iso_code as language,
                g.name as game_name,
                ARRAY_AGG(DISTINCT vdl.game_version_id ORDER BY vdl.game_version_id) as version_ids,
                ARRAY_AGG(DISTINCT vdl.character_id ORDER BY vdl.character_id) FILTER (WHERE vdl.character_id IS NOT NULL) as character_ids
            FROM unique_dialogue_texts udt
            INNER JOIN version_dialogue_lines vdl ON udt.id = vdl.text_id
            INNER JOIN game_versions gv ON vdl.game_version_id = gv.id
            INNER JOIN games g ON gv.game_id = g.id
            GROUP BY udt.id, gv.game_id, udt.text_content, vdl.iso_code, g.name
        ');

        return collect($results)->map(function ($row) {
            // Get character names for the character IDs
            $characterNames = [];
            if ($row->character_ids) {
                $characterIds = is_array($row->character_ids)
                    ? $row->character_ids
                    : static::parsePostgresArray($row->character_ids);

                if (! empty($characterIds)) {
                    $characters = Character::whereIn('id', $characterIds)->get();
                    foreach ($characters as $character) {
                        $displayNames = $character->display_names;
                        if (is_array($displayNames) && ! empty($displayNames)) {
                            $characterNames[] = reset($displayNames);
                        } else {
                            $characterNames[] = $character->character_id;
                        }
                    }
                }
            }

            $model = new static;
            $model->id = $row->text_id.'_'.$row->game_id.'_'.$row->language;
            $model->text_id = $row->text_id;
            $model->game_id = $row->game_id;
            $model->text_content = $row->text_content;
            $model->language = $row->language;
            $model->game_name = $row->game_name;

            // Parse PostgreSQL arrays
            $model->version_ids = is_array($row->version_ids)
                ? $row->version_ids
                : static::parsePostgresArray($row->version_ids);

            $model->character_ids = is_array($row->character_ids)
                ? $row->character_ids
                : static::parsePostgresArray($row->character_ids);

            $model->character_names = $characterNames;

            $model->exists = true;

            return $model;
        });
    }

    /**
     * Get dialogue texts for a specific game.
     */
    public static function getForGame(int $gameId): Collection
    {
        $characterNamesById = static::getCharacterNamesByIdForGame($gameId);

        return static::hydrateDialogueRows(
            static::queryAggregatedRowsForGame($gameId)->get(),
            $characterNamesById
        );
    }

    /**
     * Stream dialogue texts for a specific game in bounded chunks.
     *
     * @param  callable(Collection<int, static>): void  $callback
     */
    public static function chunkForGame(int $gameId, int $chunkSize, callable $callback): int
    {
        $total = 0;
        $characterNamesById = static::getCharacterNamesByIdForGame($gameId);

        static::queryAggregatedRowsForGame($gameId)
            ->orderBy('udt.id')
            ->orderBy('vdl.iso_code')
            ->chunk($chunkSize, function (Collection $rows) use ($callback, $characterNamesById, &$total) {
                $dialogueTexts = static::hydrateDialogueRows($rows, $characterNamesById);
                $total += $dialogueTexts->count();

                if ($dialogueTexts->isNotEmpty()) {
                    $callback($dialogueTexts);
                }
            });

        return $total;
    }

    protected static function queryAggregatedRowsForGame(int $gameId): Builder
    {
        return DB::table('unique_dialogue_texts as udt')
            ->join('version_dialogue_lines as vdl', 'udt.id', '=', 'vdl.text_id')
            ->join('game_versions as gv', 'vdl.game_version_id', '=', 'gv.id')
            ->join('games as g', 'gv.game_id', '=', 'g.id')
            ->where('gv.game_id', $gameId)
            ->groupBy('udt.id', 'gv.game_id', 'udt.text_content', 'vdl.iso_code', 'g.name')
            ->selectRaw('
                udt.id as text_id,
                gv.game_id,
                udt.text_content,
                vdl.iso_code as language,
                g.name as game_name,
                ARRAY_AGG(DISTINCT vdl.game_version_id ORDER BY vdl.game_version_id) as version_ids,
                ARRAY_AGG(DISTINCT vdl.character_id ORDER BY vdl.character_id) FILTER (WHERE vdl.character_id IS NOT NULL) as character_ids
            ');
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  array<int, string>  $characterNamesById
     * @return Collection<int, static>
     */
    protected static function hydrateDialogueRows(Collection $rows, array $characterNamesById): Collection
    {
        return $rows->map(function ($row) use ($characterNamesById) {
            $versionIds = is_array($row->version_ids)
                ? $row->version_ids
                : static::parsePostgresArray($row->version_ids);

            $characterIds = is_array($row->character_ids)
                ? $row->character_ids
                : static::parsePostgresArray($row->character_ids);

            $model = new static;
            $model->id = $row->text_id.'_'.$row->game_id.'_'.$row->language;
            $model->text_id = $row->text_id;
            $model->game_id = $row->game_id;
            $model->text_content = $row->text_content;
            $model->language = $row->language;
            $model->game_name = $row->game_name;
            $model->version_ids = $versionIds;
            $model->character_ids = $characterIds;
            $model->character_names = collect($characterIds)
                ->map(fn ($characterId) => $characterNamesById[(int) $characterId] ?? null)
                ->filter()
                ->values()
                ->all();
            $model->exists = true;

            return $model;
        });
    }

    /**
     * @return array<int, string>
     */
    protected static function getCharacterNamesByIdForGame(int $gameId): array
    {
        return DB::table('characters')
            ->where('game_id', $gameId)
            ->get(['id', 'character_id', 'display_names'])
            ->mapWithKeys(function ($character) {
                return [(int) $character->id => static::displayNameForCharacterRow($character)];
            })
            ->all();
    }

    protected static function displayNameForCharacterRow(object $character): string
    {
        $displayNames = $character->display_names;

        if (is_string($displayNames)) {
            $displayNames = json_decode($displayNames, true) ?: [];
        }

        if (is_array($displayNames) && ! empty($displayNames)) {
            return (string) reset($displayNames);
        }

        return (string) $character->character_id;
    }

    /**
     * Parse PostgreSQL array format to PHP array.
     */
    protected static function parsePostgresArray(?string $pgArray): array
    {
        if (empty($pgArray) || $pgArray === '{}') {
            return [];
        }

        // Remove curly braces
        $pgArray = trim($pgArray, '{}');

        if (empty($pgArray)) {
            return [];
        }

        // Split by comma and convert to integers
        return array_map('intval', explode(',', $pgArray));
    }

    /**
     * Get the indexable data array for the model.
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'text_id' => $this->text_id,
            'game_id' => $this->game_id,
            'text_content' => $this->text_content,
            'language' => $this->language,
            'game_name' => $this->game_name,
            'version_ids' => $this->version_ids ?? [],
            'character_ids' => $this->character_ids ?? [],
            'character_names' => $this->character_names ?? [],
        ];
    }

    /**
     * Get the name of the index associated with the model.
     */
    public function searchableAs(): string
    {
        return 'game_dialogue_texts';
    }

    /**
     * Determine if the model should be searchable.
     */
    public function shouldBeSearchable(): bool
    {
        // Only index texts that have actual content
        return ! empty(trim($this->text_content ?? ''));
    }

    /**
     * Get the Scout key for the model.
     */
    public function getScoutKey()
    {
        return $this->id;
    }

    /**
     * Get the Scout key name for the model.
     */
    public function getScoutKeyName()
    {
        return 'id';
    }
}
