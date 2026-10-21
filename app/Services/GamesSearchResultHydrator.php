<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GamesSearchResultHydrator
{
    private const CARD_FIELDS = [
        'id',
        'name',
        'effective_name',
        'slug',
        'status',
        'authors',
        'thumb_url',
        'optimized_thumbnails',
        'platform',
        'rating_score',
        'rating_count',
        'is_nsfw',
        'is_paid',
        'has_demo',
        'is_on_sale',
        'is_windows',
        'is_linux',
        'is_mac',
        'is_android',
        'is_web',
        'initially_published_at',
        'latest_version_published_at',
        'latest_version_number',
        'english_word_count',
        'primary_word_count',
        'primary_language_name',
        'supported_languages',
        'tags',
        'user_progress',
        'user_list_memberships',
        'user_private_tags',
    ];

    public static function stripForList(mixed $game): void
    {
        if (! method_exists($game, 'setVisible')) {
            return;
        }

        $game->setVisible(self::CARD_FIELDS);

        if ($game->relationLoaded('tags')) {
            $game->setRelation('tags', $game->tags->map->only(['id', 'name']));
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

    /**
     * @param  array<int, int>  $gameIds
     * @return Collection<int, array<int, array{id: int, name: string}>>
     */
    public static function privateTagsByGame(int $userId, array $gameIds): Collection
    {
        if ($gameIds === []) {
            return collect();
        }

        return DB::table('private_tag_games')
            ->join('private_tags', 'private_tags.id', '=', 'private_tag_games.private_tag_id')
            ->where('private_tags.user_id', $userId)
            ->whereIn('private_tag_games.game_id', $gameIds)
            ->orderByRaw('lower(private_tags.name)')
            ->select('private_tag_games.game_id', 'private_tags.id', 'private_tags.name')
            ->get()
            ->groupBy('game_id')
            ->map(fn (Collection $tags) => $tags->map(fn ($tag) => ['id' => $tag->id, 'name' => $tag->name])->values()->all());
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
        $userData = $this->userData($items->pluck('id')->all(), $userId);

        foreach ($items as $game) {
            foreach ($userData[$game->id] ?? [] as $field => $value) {
                $game->{$field} = $value;
            }
        }
    }

    /**
     * @param  list<int>  $gameIds
     * @return array<int, array{user_progress: list<object>, user_list_memberships: list<object>, user_private_tags: list<array{id: int, name: string}>}>
     */
    public function userData(array $gameIds, int $userId): array
    {
        if ($gameIds === []) {
            return [];
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

        $privateTags = self::privateTagsByGame($userId, $gameIds);

        $userData = [];
        foreach ($gameIds as $gameId) {
            $progress = $userProgress->get($gameId);
            $userData[$gameId] = [
                'user_progress' => $progress ? [$progress] : [],
                'user_list_memberships' => $userListMemberships->get($gameId, collect())->all(),
                'user_private_tags' => $privateTags->get($gameId, []),
            ];
        }

        return $userData;
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
