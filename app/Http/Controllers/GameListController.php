<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class GameListController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $visibilityScope = fn ($query) => ! auth()->user()?->can('viewHidden', Game::class)
            ? $query->where('is_visible', true)
            : $query;

        $filterOptions = Cache::remember('game-filter-options', 3600, function () use ($visibilityScope) {
            $baseQuery = Game::query()->tap($visibilityScope);

            return [
                'statuses' => $baseQuery->clone()
                    ->select('status')
                    ->whereNotNull('status')
                    ->distinct()
                    ->orderBy('status')
                    ->pluck('status', 'status')
                    ->all(),

                'gameEngines' => $baseQuery->clone()
                    ->select('game_engine')
                    ->whereNotNull('game_engine')
                    ->distinct()
                    ->orderBy('game_engine')
                    ->pluck('game_engine', 'game_engine')
                    ->all(),

                'platforms' => [
                    'windows' => 'Windows',
                    'linux' => 'Linux',
                    'mac' => 'Mac',
                    'android' => 'Android',
                    'web' => 'Web',
                ],
            ];
        });

        $filterOptions['languages'] = Cache::remember('game-languages', 86400, function () {
            return Language::query()
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
                ])
                ->all();
        });

        return Inertia::render('GameList', [
            'filterOptions' => $filterOptions,
        ]);
    }
}
