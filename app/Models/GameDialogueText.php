<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Searchable;
use Meilisearch\Client;

/**
 * GameDialogueText represents unique dialogue texts aggregated per game.
 * This is a virtual model (no database table) used solely for Meilisearch indexing.
 * Each record represents one unique text in the current indexed version of one game,
 * with metadata about where the text first appeared historically.
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
        // Query to aggregate dialogue texts from each game's current dialogue version.
        $results = DB::select('
            WITH current_versions AS (
                SELECT DISTINCT ON (gv.game_id)
                    gv.id,
                    gv.game_id
                FROM game_versions gv
                WHERE EXISTS (
                    SELECT 1
                    FROM version_dialogue_lines vdl
                    WHERE vdl.game_version_id = gv.id
                    LIMIT 1
                )
                ORDER BY gv.game_id, gv.is_latest DESC, gv.published_at DESC NULLS LAST, gv.id DESC
            ),
            current_texts AS (
                SELECT
                    udt.id as text_id,
                    gv.game_id,
                    udt.text_content,
                    vdl.iso_code as language,
                    g.name as game_name,
                    gv.id as current_version_id,
                    gv.version as current_version,
                    gv.published_at as current_version_published_at,
                    ARRAY_AGG(DISTINCT vdl.character_id ORDER BY vdl.character_id) FILTER (WHERE vdl.character_id IS NOT NULL) as character_ids
                FROM unique_dialogue_texts udt
                INNER JOIN version_dialogue_lines vdl ON udt.id = vdl.text_id
                INNER JOIN current_versions cv ON cv.id = vdl.game_version_id
                INNER JOIN game_versions gv ON vdl.game_version_id = gv.id
                INNER JOIN games g ON gv.game_id = g.id
                GROUP BY udt.id, gv.game_id, udt.text_content, vdl.iso_code, g.name, gv.id, gv.version, gv.published_at
            ),
            first_seen AS (
                SELECT DISTINCT ON (gv.game_id, vdl.text_id, vdl.iso_code)
                    gv.game_id,
                    vdl.text_id,
                    vdl.iso_code as language,
                    gv.id as first_seen_version_id,
                    gv.version as first_seen_version,
                    gv.published_at as first_seen_published_at
                FROM version_dialogue_lines vdl
                INNER JOIN game_versions gv ON vdl.game_version_id = gv.id
                ORDER BY gv.game_id, vdl.text_id, vdl.iso_code, gv.published_at ASC NULLS LAST, gv.id ASC
            )
            SELECT
                current_texts.*,
                first_seen.first_seen_version_id,
                first_seen.first_seen_version,
                first_seen.first_seen_published_at
            FROM current_texts
            INNER JOIN first_seen
                ON first_seen.game_id = current_texts.game_id
                AND first_seen.text_id = current_texts.text_id
                AND first_seen.language = current_texts.language
        ');

        return collect($results)->map(fn ($row) => static::fromIndexRow($row));
    }

    /**
     * Get dialogue texts for a specific game.
     */
    public static function getForGame(int $gameId): Collection
    {
        $results = DB::select('
            WITH current_version AS (
                SELECT gv.id
                FROM game_versions gv
                WHERE gv.game_id = ?
                AND EXISTS (
                    SELECT 1
                    FROM version_dialogue_lines vdl
                    WHERE vdl.game_version_id = gv.id
                    LIMIT 1
                )
                ORDER BY gv.is_latest DESC, gv.published_at DESC NULLS LAST, gv.id DESC
                LIMIT 1
            ),
            current_texts AS (
                SELECT
                    udt.id as text_id,
                    gv.game_id,
                    udt.text_content,
                    vdl.iso_code as language,
                    g.name as game_name,
                    gv.id as current_version_id,
                    gv.version as current_version,
                    gv.published_at as current_version_published_at,
                    ARRAY_AGG(DISTINCT vdl.character_id ORDER BY vdl.character_id) FILTER (WHERE vdl.character_id IS NOT NULL) as character_ids
                FROM unique_dialogue_texts udt
                INNER JOIN version_dialogue_lines vdl ON udt.id = vdl.text_id
                INNER JOIN current_version cv ON cv.id = vdl.game_version_id
                INNER JOIN game_versions gv ON vdl.game_version_id = gv.id
                INNER JOIN games g ON gv.game_id = g.id
                GROUP BY udt.id, gv.game_id, udt.text_content, vdl.iso_code, g.name, gv.id, gv.version, gv.published_at
            ),
            first_seen AS (
                SELECT DISTINCT ON (vdl.text_id, vdl.iso_code)
                    vdl.text_id,
                    vdl.iso_code as language,
                    gv.id as first_seen_version_id,
                    gv.version as first_seen_version,
                    gv.published_at as first_seen_published_at
                FROM version_dialogue_lines vdl
                INNER JOIN game_versions gv ON vdl.game_version_id = gv.id
                WHERE gv.game_id = ?
                ORDER BY vdl.text_id, vdl.iso_code, gv.published_at ASC NULLS LAST, gv.id ASC
            )
            SELECT
                current_texts.*,
                first_seen.first_seen_version_id,
                first_seen.first_seen_version,
                first_seen.first_seen_published_at
            FROM current_texts
            INNER JOIN first_seen
                ON first_seen.text_id = current_texts.text_id
                AND first_seen.language = current_texts.language
        ', [$gameId, $gameId]);

        return collect($results)->map(fn ($row) => static::fromIndexRow($row));
    }

    public static function deleteSearchDocumentsForGame(int $gameId): void
    {
        app(Client::class)
            ->index('game_dialogue_texts')
            ->deleteDocuments(['filter' => 'game_id = '.$gameId]);
    }

    public static function deleteAllSearchDocuments(): void
    {
        app(Client::class)
            ->index('game_dialogue_texts')
            ->deleteAllDocuments();
    }

    protected static function fromIndexRow(object $row): self
    {
        $characterIds = is_array($row->character_ids)
            ? $row->character_ids
            : static::parsePostgresArray($row->character_ids);

        $characterNames = [];
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

        $model = new static;
        $model->id = $row->text_id.'_'.$row->game_id.'_'.$row->language;
        $model->text_id = $row->text_id;
        $model->game_id = $row->game_id;
        $model->text_content = $row->text_content;
        $model->language = $row->language;
        $model->game_name = $row->game_name;
        $model->current_version_id = (int) $row->current_version_id;
        $model->current_version = $row->current_version;
        $model->current_version_published_at = $row->current_version_published_at;
        $model->version_ids = [(int) $row->current_version_id];
        $model->character_ids = $characterIds;
        $model->character_names = $characterNames;
        $model->first_seen_version_id = (int) $row->first_seen_version_id;
        $model->first_seen_version = $row->first_seen_version;
        $model->first_seen_published_at = $row->first_seen_published_at;
        $model->exists = true;

        return $model;
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
            'current_version_id' => $this->current_version_id,
            'current_version' => $this->current_version,
            'current_version_published_at' => $this->formatDateForSearch($this->current_version_published_at ?? null),
            'version_ids' => $this->version_ids ?? [],
            'character_ids' => $this->character_ids ?? [],
            'character_names' => $this->character_names ?? [],
            'first_seen_version_id' => $this->first_seen_version_id,
            'first_seen_version' => $this->first_seen_version,
            'first_seen_published_at' => $this->formatDateForSearch($this->first_seen_published_at ?? null),
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

    private function formatDateForSearch(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return substr((string) $value, 0, 10);
    }
}
