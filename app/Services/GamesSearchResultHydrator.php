<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GamesSearchResultHydrator
{
    private const LIST_HIDDEN = [
        'description',
        'full_description',
        'custom_description',
        'custom_css',
        'custom_assets',
        'screenshots',
        'uploads',
        'additional_links',
        'steam_genres',
        'steam_user_tags',
        'discord_tags',
        'discord_channel_id',
        'discord_message_id',
        'discord_likes',
        'discord_dislikes',
        'discord_updated_at',
        'abbreviations',
        'content_type',
        'is_stats_extraction_disabled',
        'trending_score_calculated_at',
        'custom_page_updated_at',
        'custom_page_updated_by',
        'custom_name',
        'url',
        'min_price',
        'currency',
        'sale_discount_percent',
    ];

    public static function stripForList(mixed $game): void
    {
        if (! method_exists($game, 'makeHidden')) {
            return;
        }

        $game->makeHidden(self::LIST_HIDDEN);

        if (method_exists($game, 'relationLoaded')) {
            foreach (['latestVersion', 'sourceLanguage'] as $relation) {
                if ($game->relationLoaded($relation)) {
                    $game->unsetRelation($relation);
                }
            }
        }
    }

    public static function hydrateWordCounts(mixed $game): void
    {
        if (! $game->latestVersion) {
            $game->english_word_count = null;
            $game->primary_word_count = null;
            $game->primary_language_name = 'English';

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

        $game->primary_language_name = $sourceLanguageId === 'eng'
            ? 'English'
            : ($game->sourceLanguage?->ref_name ?? strtoupper($sourceLanguageId));
    }

    private static function orderTagsByPopularity(mixed $game): void
    {
        if (! method_exists($game, 'relationLoaded') || ! $game->relationLoaded('tags')) {
            return;
        }

        $counts = Tag::popularGameCounts();

        $game->setRelation('tags', $game->tags
            ->sortBy([
                fn (Tag $a, Tag $b) => ($counts[$b->id] ?? 0) <=> ($counts[$a->id] ?? 0),
                fn (Tag $a, Tag $b) => strcasecmp($a->name, $b->name),
            ])
            ->values());
    }

    public function hydrate(mixed $games): void
    {
        if ($games->count() <= 0) {
            return;
        }

        $collection = $games->getCollection();
        if ($collection instanceof EloquentCollection) {
            $this->hydrateModels($collection);

            return;
        }

        foreach ($collection as $game) {
            $this->hydrateGame($game);
            self::stripForList($game);
        }
    }

    public function hydrateModels(EloquentCollection $games): void
    {
        if ($games->isEmpty()) {
            return;
        }

        $games->load([
            'tags' => Tag::constrainToPopular(),
            'sourceLanguage',
            'latestVersion.supportedLanguages.language',
            'latestVersion.languageStats',
        ]);

        foreach ($games as $game) {
            $this->hydrateGame($game);
            self::stripForList($game);
        }
    }

    public function attachUserData(mixed $games, int $userId): void
    {
        if ($games->count() <= 0) {
            return;
        }

        $items = $games instanceof Collection ? $games : collect($games->items());
        $gameIds = $items->pluck('id')->toArray();

        if (empty($gameIds)) {
            return;
        }

        $userProgress = DB::table('user_game_progress')
            ->where('user_id', $userId)
            ->whereIn('game_id', $gameIds)
            ->select('game_id', 'is_receiving_updates')
            ->get()
            ->keyBy('game_id');

        $userListMemberships = DB::table('vn_list_entries')
            ->join('vn_lists', 'vn_list_entries.vn_list_id', '=', 'vn_lists.id')
            ->where('vn_lists.user_id', $userId)
            ->whereIn('vn_list_entries.game_id', $gameIds)
            ->select('vn_list_entries.game_id', 'vn_lists.id as list_id', 'vn_lists.name', 'vn_lists.type', 'vn_lists.is_default', 'vn_lists.is_public')
            ->get()
            ->groupBy('game_id');

        foreach ($items as $game) {
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
            $game->latest_version_number = $game->latestVersion->version;
        } else {
            $game->is_windows = false;
            $game->is_linux = false;
            $game->is_mac = false;
            $game->is_android = false;
            $game->is_web = false;
            $game->latest_version_id = null;
            $game->latest_version_published_at = null;
            $game->latest_version_number = null;
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

        self::hydrateWordCounts($game);
        self::orderTagsByPopularity($game);
    }
}
