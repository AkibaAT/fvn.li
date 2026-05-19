<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

class GamesSearchResultHydrator
{
    public function hydrate(mixed $games): void
    {
        if ($games->count() <= 0) {
            return;
        }

        $collection = $games->getCollection();
        if (method_exists($collection, 'load')) {
            $collection->load([
                'tags',
                'sourceLanguage',
                'latestVersion.supportedLanguages.language',
                'latestVersion.languageStats',
            ]);
        }

        foreach ($games as $game) {
            $this->hydrateGame($game);
        }
    }

    public function attachUserData(mixed $games, int $userId): void
    {
        if ($games->count() <= 0) {
            return;
        }

        $gameIds = collect($games->items())->pluck('id')->toArray();

        if (empty($gameIds)) {
            return;
        }

        $userProgress = DB::table('user_game_progress')
            ->where('user_id', $userId)
            ->whereIn('game_id', $gameIds)
            ->select('game_id', 'receive_updates')
            ->get()
            ->keyBy('game_id');

        $userListMemberships = DB::table('vn_list_entries')
            ->join('vn_lists', 'vn_list_entries.vn_list_id', '=', 'vn_lists.id')
            ->where('vn_lists.user_id', $userId)
            ->whereIn('vn_list_entries.game_id', $gameIds)
            ->select('vn_list_entries.game_id', 'vn_lists.id as list_id', 'vn_lists.name', 'vn_lists.type', 'vn_lists.is_default')
            ->get()
            ->groupBy('game_id');

        foreach ($games->items() as $game) {
            $progress = $userProgress->get($game->id);
            $game->user_progress = $progress ? [$progress] : [];
            $game->user_list_memberships = $userListMemberships->get($game->id, collect())->toArray();
        }
    }

    private function hydrateGame(mixed $game): void
    {
        if ($game->latestVersion) {
            $game->is_windows = $game->latestVersion->is_windows ?? false;
            $game->is_linux = $game->latestVersion->is_linux ?? false;
            $game->is_mac = $game->latestVersion->is_mac ?? false;
            $game->is_android = $game->latestVersion->is_android ?? false;
            $game->is_web = $game->latestVersion->is_web ?? false;
            $game->latest_version_id = $game->latestVersion->id;
            $game->latest_version_published_at = $game->latestVersion->published_at;
        } else {
            $game->is_windows = false;
            $game->is_linux = false;
            $game->is_mac = false;
            $game->is_android = false;
            $game->is_web = false;
            $game->latest_version_id = null;
            $game->latest_version_published_at = null;
        }

        $game->supported_languages = $game->latestVersion && $game->latestVersion->supportedLanguages
            ? $game->latestVersion->supportedLanguages
                ->where('is_available', true)
                ->map(fn ($supportedLanguage) => [
                    'iso_code' => $supportedLanguage->iso_code,
                    'is_available' => $supportedLanguage->is_available,
                    'ref_name' => $supportedLanguage->language?->ref_name,
                    'flag_code' => $supportedLanguage->language?->flag_code,
                ])
                ->values()
            : collect();

        if (! $game->latestVersion) {
            $game->english_word_count = null;
            $game->primary_word_count = null;
            $game->primary_language_label = 'EN';

            return;
        }

        $englishStats = $game->latestVersion->languageStats
            ->where('iso_code', 'eng')
            ->first();
        $game->english_word_count = $englishStats?->words;

        $sourceLanguageId = $game->source_language_id ?? 'eng';
        if ($sourceLanguageId !== 'eng') {
            $primaryStats = $game->latestVersion->languageStats
                ->where('iso_code', $sourceLanguageId)
                ->first();
            $game->primary_word_count = $primaryStats?->words;
        } else {
            $game->primary_word_count = $game->english_word_count;
        }

        $game->primary_language_label = $game->getPrimaryLanguageLabel();
    }
}
