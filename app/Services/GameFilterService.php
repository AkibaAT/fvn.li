<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\GameJam;
use App\Models\Language;
use App\Models\Tag;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GameFilterService
{
    public static function clearCache(): void
    {
        Cache::forget('react-game-filter-options');
    }

    public static function getOptions(): array
    {
        return Cache::remember('react-game-filter-options', 3600, function () {
            $gameIds = DB::table('game_versions')
                ->where('is_latest', true)
                ->pluck('game_id');

            $games = Game::whereIn('id', $gameIds)
                ->select([
                    'status',
                    'game_engine',
                    'is_nsfw',
                    'is_paid',
                    'has_demo',
                ])
                ->get();

            $statuses = $games->pluck('status')
                ->unique()
                ->filter()
                ->sort()
                ->mapWithKeys(fn ($status) => [
                    $status => $status,
                ]);

            $engines = $games->pluck('game_engine')
                ->unique()
                ->filter()
                ->sort()
                ->mapWithKeys(fn ($engine) => [
                    $engine => $engine,
                ]);

            $languages = Language::query()
                ->whereExists(function ($query) {
                    $query->select('version_supported_languages.id')
                        ->from('version_supported_languages')
                        ->whereColumn('version_supported_languages.iso_code', 'iso_639_3_languages.id')
                        ->limit(1);
                })
                ->orderBy('ref_name')
                ->get()
                ->mapWithKeys(fn ($lang) => [
                    $lang->id => [
                        'ref_name' => $lang->ref_name,
                        'flag_code' => $lang->flag_code,
                    ],
                ]);

            if ($languages->count() === 0) {
                $languages = Language::query()
                    ->whereExists(function ($query) {
                        $query->select('games.id')
                            ->from('games')
                            ->whereColumn('games.source_language_id', 'iso_639_3_languages.id')
                            ->where('games.is_visible', true)
                            ->limit(1);
                    })
                    ->orderBy('ref_name')
                    ->get()
                    ->mapWithKeys(fn ($lang) => [
                        $lang->id => [
                            'ref_name' => $lang->ref_name,
                            'flag_code' => $lang->flag_code,
                        ],
                    ]);
            }

            if ($languages->count() === 0) {
                $commonLanguages = ['eng', 'jpn', 'fra', 'deu', 'spa', 'rus', 'kor', 'zho'];
                $languages = Language::query()
                    ->whereIn('id', $commonLanguages)
                    ->orderBy('ref_name')
                    ->get()
                    ->mapWithKeys(fn ($lang) => [
                        $lang->id => [
                            'ref_name' => $lang->ref_name,
                            'flag_code' => $lang->flag_code,
                        ],
                    ]);
            }

            $gameJams = GameJam::query()
                ->whereExists(function ($query) {
                    $query->select('game_game_jam.id')
                        ->from('game_game_jam')
                        ->join('games', function ($join) {
                            $join->on('games.id', '=', 'game_game_jam.game_id')
                                ->where('is_visible', true);
                        })
                        ->whereColumn('game_game_jam.game_jam_id', 'game_jams.id')
                        ->limit(1);
                })
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn ($jam) => [
                    (string) $jam->id => $jam->name,
                ]);

            $tags = Tag::query()
                ->withCount('games')
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn ($tag) => [
                    (string) $tag->id => $tag->name.' ('.$tag->games_count.')',
                ]);

            return [
                'statuses' => $statuses->all(),
                'gameEngines' => $engines->all(),
                'platforms' => [
                    'windows' => 'Windows',
                    'linux' => 'Linux',
                    'mac' => 'macOS',
                    'android' => 'Android',
                    'web' => 'Web',
                ],
                'storePlatforms' => [
                    'itch_io' => 'itch.io',
                    'steam' => 'Steam',
                    'other' => 'Other',
                ],
                'languages' => $languages->all(),
                'gameJams' => $gameJams->all(),
                'tags' => $tags->all(),
                'sortOptions' => [
                    'relevance' => 'Relevance',
                    'first_visible_at' => 'Recently Added',
                    'latest_version_published_at' => 'Latest Update',
                    'trending' => 'Trending',
                    'english_word_count' => 'Word Count',
                    'rating_count' => 'Most Rated',
                    'rating_score' => 'Highest Rated',
                    'name' => 'Name',
                    'initially_published_at' => 'Release Date',
                ],
                'readingTimeOptions' => [
                    'short' => 'Short (< 10k words)',
                    'medium' => 'Medium (10k-50k words)',
                    'long' => 'Long (> 50k words)',
                ],
            ];
        });
    }
}
