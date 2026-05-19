<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameVersion;
use Illuminate\Support\Facades\DB;

class RouteGraphWordCountService
{
    /**
     * Compute approximate source-language word counts per route label.
     *
     * @return array<string, int> label name => word count
     */
    public function byLabel(GameVersion $version): array
    {
        $game = $version->game ?? $version->game()->first();
        $isoCode = $game?->source_language_id ?? 'eng';

        $rows = DB::table('version_dialogue_lines')
            ->join('unique_dialogue_texts', 'version_dialogue_lines.text_id', '=', 'unique_dialogue_texts.id')
            ->where('version_dialogue_lines.game_version_id', $version->id)
            ->where('version_dialogue_lines.iso_code', $isoCode)
            ->whereNotNull('version_dialogue_lines.context')
            ->groupBy('version_dialogue_lines.context')
            ->select([
                'version_dialogue_lines.context',
                DB::raw("SUM(
                    CASE WHEN trim(unique_dialogue_texts.text_content) = '' THEN 0
                    ELSE array_length(string_to_array(trim(unique_dialogue_texts.text_content), ' '), 1)
                    END
                ) as word_count"),
            ])
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$row->context] = (int) $row->word_count;
        }

        return $result;
    }
}
