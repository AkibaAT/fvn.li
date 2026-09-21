<?php

declare(strict_types=1);

namespace App\Http\Controllers\Games;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Tag;
use App\Services\GamesSearchResultHydrator;
use App\Services\MeilisearchService;
use App\Support\Seo\MetaTags;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class GameTagsController extends Controller
{
    private const PER_PAGE = 24;

    private const RELATED_TAG_LIMIT = 12;

    public function index(): Response
    {
        $counts = $this->visibleGameCounts();

        $tags = Tag::query()
            ->whereIn('id', array_keys(array_filter($counts)))
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (Tag $tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'games_count' => $counts[$tag->id],
            ])
            ->values();

        $popular = $tags->sortByDesc('games_count')->take(5)->pluck('name')->implode(', ');

        $metaTags = new MetaTags(
            title: 'Furry Visual Novels by Tag',
            description: "Browse {$tags->count()} tags across the furry visual novel catalogue on FVN.li, including {$popular}.",
            image: asset(config('social.images.games_list', config('social.images.default'))),
            url: route('tags.index'),
            structuredData: [
                '@graph' => [
                    [
                        '@type' => 'CollectionPage',
                        'name' => 'Furry Visual Novels by Tag',
                        'url' => route('tags.index'),
                    ],
                    $this->breadcrumbs([['Visual Novels', route('games.index')], ['Tags', route('tags.index')]]),
                ],
            ],
        );

        return Inertia::render('tags/index', [
            'tags' => $tags,
            'metaTags' => $metaTags->toArray(),
        ]);
    }

    public function show(Request $request, Tag $tag, MeilisearchService $service): Response
    {
        abort_unless(in_array($tag->id, Tag::popularIds(), true), 404);
        $counts = $this->visibleGameCounts();

        $page = max(1, (int) $request->get('page', 1));
        $games = $this->games($service, $tag, $page);
        abort_if($page > 1 && $page > $games->lastPage(), 404);

        $games->withPath(route('tags.show', $tag));

        $hydrator = app(GamesSearchResultHydrator::class);
        $hydrator->hydrate($games);

        $ignoredGameIds = [];
        if (Auth::check()) {
            $hydrator->attachUserData($games, Auth::id());
            $ignoredGameIds = Auth::user()->ignoredGames()->pluck('games.id')->toArray();
        }

        $title = "{$tag->name} Furry Visual Novels";
        $examples = collect($games->items())->take(3)->pluck('effective_name')->implode(', ');

        $metaTags = new MetaTags(
            title: $title,
            browserTitle: $page > 1 ? "{$title} – Page {$page}" : $title,
            description: "Browse {$games->total()} furry visual novels tagged {$tag->name} on FVN.li" . ($examples !== '' ? ", including {$examples}." : '.'),
            image: collect($games->items())->first()?->optimized_thumbnail_url ?? asset(config('social.images.games_list', config('social.images.default'))),
            url: route('tags.show', $tag),
            structuredData: [
                '@graph' => [
                    [
                        '@type' => 'CollectionPage',
                        'name' => $title,
                        'url' => route('tags.show', $tag),
                        'mainEntity' => [
                            '@type' => 'ItemList',
                            'numberOfItems' => $games->total(),
                            'itemListElement' => collect($games->items())->values()->map(fn (Game $game, int $index) => [
                                '@type' => 'ListItem',
                                'position' => ($games->currentPage() - 1) * $games->perPage() + $index + 1,
                                'url' => route('games.show', $game),
                                'name' => $game->effective_name,
                            ])->all(),
                        ],
                    ],
                    $this->breadcrumbs([
                        ['Visual Novels', route('games.index')],
                        ['Tags', route('tags.index')],
                        [$tag->name, route('tags.show', $tag)],
                    ]),
                ],
            ],
        );

        return Inertia::render('tags/show', [
            'tag' => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'games_count' => $games->total(),
            ],
            'games' => $games,
            'relatedTags' => $this->relatedTags($tag, $counts),
            'catalogueUrl' => route('games.index', ['selectedTags' => [$tag->id], 'noDefaults' => 1]),
            'ignoredGameIds' => $ignoredGameIds,
            'metaTags' => $metaTags->toArray(),
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function visibleGameCounts(): array
    {
        return Cache::remember('tags.visible_game_counts.' . Tag::publicCacheVariant(), 3600, fn () => DB::table('game_tag')
            ->select('game_tag.tag_id', DB::raw('COUNT(*) as games_count'))
            ->join('games', 'games.id', '=', 'game_tag.game_id')
            ->where('games.is_visible', true)
            ->whereIn('game_tag.tag_id', Tag::popularIds())
            ->groupBy('game_tag.tag_id')
            ->pluck('games_count', 'tag_id')
            ->map(fn ($count) => (int) $count)
            ->all());
    }

    private function games(MeilisearchService $service, Tag $tag, int $page): LengthAwarePaginator
    {
        $filters = ['is_visible' => true, 'tags' => [$tag->name]];

        try {
            return $service->searchGames('*', $filters, self::PER_PAGE, $page, 'trending_score', 'desc');
        } catch (Exception $e) {
            Log::warning('Meilisearch tag page lookup failed; using database fallback', [
                'tag_id' => $tag->id,
                'error' => $e->getMessage(),
            ]);

            return $service->searchGamesFromDatabase('*', $filters, self::PER_PAGE, $page, 'trending_score', 'desc', []);
        }
    }

    /**
     * @param  array<int, int>  $counts
     * @return list<array{name: string, slug: string, games_count: int}>
     */
    private function relatedTags(Tag $tag, array $counts): array
    {
        $relatedIds = DB::table('game_tag as source')
            ->join('game_tag as related', 'related.game_id', '=', 'source.game_id')
            ->join('games', 'games.id', '=', 'source.game_id')
            ->where('source.tag_id', $tag->id)
            ->where('related.tag_id', '!=', $tag->id)
            ->whereIn('related.tag_id', array_keys(array_filter($counts)))
            ->where('games.is_visible', true)
            ->groupBy('related.tag_id')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(self::RELATED_TAG_LIMIT)
            ->pluck('related.tag_id')
            ->all();

        $tags = Tag::query()->whereIn('id', $relatedIds)->get(['id', 'name', 'slug'])->keyBy('id');

        return collect($relatedIds)
            ->map(fn (int $id) => $tags->get($id))
            ->filter()
            ->map(fn (Tag $related) => [
                'name' => $related->name,
                'slug' => $related->slug,
                'games_count' => $counts[$related->id],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{0: string, 1: string}>  $crumbs
     */
    private function breadcrumbs(array $crumbs): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($crumbs)->values()->map(fn (array $crumb, int $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb[0],
                'item' => $crumb[1],
            ])->all(),
        ];
    }
}
