<?php

declare(strict_types=1);

namespace App\Http\Controllers\VnLists;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\User;
use App\Models\VnList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class PublicVnListController extends Controller
{
    private const int PUBLIC_LISTS_DEFAULT_PER_PAGE = 8;

    private const int PUBLIC_LISTS_MAX_PER_PAGE = 24;

    private const int PUBLIC_LISTS_MAX_SEARCH_LENGTH = 80;

    private const int PUBLIC_LISTS_MIN_SEARCH_LENGTH = 2;

    private const array PUBLIC_LIST_TYPES = [
        'all',
        'plan_to_read',
        'reading',
        'completed',
        'on_hold',
        'dropped',
        'custom',
    ];

    private const array PUBLIC_LIST_SORTS = [
        'default',
        'newest',
        'oldest',
        'most_entries',
        'recently_updated',
    ];

    public function publicLists(Request $request): Response
    {
        $perPage = min(
            self::PUBLIC_LISTS_MAX_PER_PAGE,
            max(1, (int) $request->input('per_page', self::PUBLIC_LISTS_DEFAULT_PER_PAGE))
        );
        $type = $this->normalizePublicListType($request->input('type', 'all'));
        $page = $this->normalizePublicListPage($request->input('page', 1));
        $search = $this->normalizePublicListSearch($request->input('search', ''));
        $sort = $this->normalizePublicListSort($request->input('sort', 'default'));
        $gameId = $this->normalizePublicListGameId($request->input('game'));

        // Load game if filtering by game
        $filterGame = null;
        if ($gameId) {
            $filterGame = Game::select('id', 'name', 'slug')->find($gameId);
        }

        // Create a unique cache key for this request
        $searchKey = $search ? md5($search) : '';
        $gameKey = $gameId ?: '';
        $cacheKey = "public_lists:{$type}:{$perPage}:{$page}:{$sort}:{$searchKey}:{$gameKey}";

        // Try to get cached data (only cache non-search/non-game-filter queries to keep them responsive)
        if (empty($search) && empty($gameId)) {
            $cachedData = Cache::get($cacheKey);
            if ($cachedData) {
                // Add current filter values to cached response
                $cachedData['search'] = $search;
                $cachedData['sort'] = $sort;
                $cachedData['filterGame'] = $filterGame;

                return Inertia::render('lists/public', $cachedData);
            }
        }

        // Get counts for each tab with a single query using conditional aggregation
        $countsQuery = VnList::where('is_public', true)
            ->has('entries');

        // Apply game filter to counts
        if ($gameId) {
            $countsQuery->whereHas('entries', function ($q) use ($gameId) {
                $q->where('game_id', $gameId);
            });
        }

        // Apply search to counts if searching (by user name or game name)
        if (! empty($search)) {
            $countsQuery->where(function ($q) use ($search) {
                $q->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'ILIKE', "%{$search}%");
                })->orWhereHas('entries.game', function ($gameQuery) use ($search) {
                    $gameQuery->where('name', 'ILIKE', "%{$search}%");
                });
            });
        }

        $counts = $countsQuery
            ->selectRaw('COUNT(*) as all_count')
            ->selectRaw('SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as plan_to_read_count', ['plan_to_read'])
            ->selectRaw('SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as reading_count', ['reading'])
            ->selectRaw('SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as completed_count', ['completed'])
            ->selectRaw('SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as on_hold_count', ['on_hold'])
            ->selectRaw('SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as dropped_count', ['dropped'])
            ->selectRaw('SUM(CASE WHEN type NOT IN (?, ?, ?, ?, ?) THEN 1 ELSE 0 END) as custom_count',
                ['plan_to_read', 'reading', 'completed', 'on_hold', 'dropped'])
            ->first();

        // Optimize the main query to only load necessary data
        $query = VnList::withCount('entries')
            ->with([
                'user' => function ($q) {
                    $q->select('id', 'name', 'avatar');
                },
                'entries' => function ($query) {
                    // Load only first 10 entries for carousel with minimal game data
                    $query->select('id', 'vn_list_id', 'game_id', 'sort_order')
                        ->orderBy('sort_order')
                        ->limit(10)
                        ->with([
                            'game' => function ($q) {
                                // Only select essential fields for the game
                                $q->select([
                                    'id', 'name', 'custom_name', 'has_custom_page', 'view_mode', 'thumb_url', 'is_nsfw',
                                    'slug', 'optimized_thumbnails', 'is_paid', 'has_demo', 'is_on_sale', 'min_price',
                                ]);
                                // Explicitly prevent tags from being loaded
                                $q->without(['tags']);
                            },
                        ]);
                },
            ])
            ->where('is_public', true)
            ->has('entries');

        // Apply search filter (by user name or game name)
        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'ILIKE', "%{$search}%");
                })->orWhereHas('entries.game', function ($gameQuery) use ($search) {
                    $gameQuery->where('name', 'ILIKE', "%{$search}%");
                });
            });
        }

        // Apply game filter
        if ($gameId) {
            $query->whereHas('entries', function ($q) use ($gameId) {
                $q->where('game_id', $gameId);
            });
        }

        // Apply type filter if provided
        if ($type !== 'all') {
            if ($type === 'custom') {
                $query->whereNotIn('type', ['plan_to_read', 'reading', 'completed', 'on_hold', 'dropped']);
            } else {
                $query->where('type', $type);
            }
        }

        // Apply sorting
        switch ($sort) {
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'most_entries':
                $query->orderByRaw('(SELECT COUNT(*) FROM vn_list_entries WHERE vn_list_entries.vn_list_id = vn_lists.id) DESC');
                break;
            case 'recently_updated':
                $query->orderBy('updated_at', 'desc');
                break;
            default:
                // Sort by type priority first, then by creation date
                $query->orderByRaw("
                    CASE type
                        WHEN 'reading' THEN 1
                        WHEN 'plan_to_read' THEN 2
                        WHEN 'completed' THEN 3
                        WHEN 'on_hold' THEN 4
                        WHEN 'dropped' THEN 5
                        ELSE 6
                    END, created_at DESC
                ");
                break;
        }

        $lists = $query->paginate($perPage, ['*'], 'page', $page);

        // Get first list for meta tag image using optimized thumbnail helper
        // Normalize game thumbnails to optimized URLs for client/preload
        $lists->getCollection()->each(function ($list) {
            $list->entries->each(function ($entry) {
                if ($entry->game) {
                    $optimized = $entry->game->getThumbnailUrl('default');
                    if ($optimized) {
                        $entry->game->setAttribute('thumb_url', $optimized);
                    }
                }
            });
        });

        $metaTags = [
            'title' => 'Public Visual Novel Lists',
            'description' => 'Browse public visual novel lists shared by the community. '.
                "Currently featuring {$lists->total()} public lists".
                ($lists->isNotEmpty() ? ', including: '.$lists->take(3)->map(function ($list) {
                    return "{$list->name} by {$list->user->name} (".$list->entries->count().' games)';
                })->implode(', ') : ''),
            'image' => asset(config('social.images.public_lists', config('social.images.default'))),
            'structuredData' => [
                '@type' => 'CollectionPage',
                'name' => 'Public Visual Novel Lists',
                'description' => 'Browse public visual novel lists shared by the community',
                'url' => route('lists.public'),
                'numberOfItems' => $lists->total(),
                'mainEntity' => [
                    '@type' => 'ItemList',
                    'name' => 'Public Visual Novel Lists',
                    'numberOfItems' => $lists->total(),
                    'itemListElement' => $lists->take(3)->map(function ($list, $index) {
                        return [
                            '@type' => 'ListItem',
                            'position' => $index + 1,
                            'item' => [
                                '@type' => 'CreativeWork',
                                'name' => $list->name,
                                'description' => $list->description ?? 'A public visual novel list',
                                'author' => [
                                    '@type' => 'Person',
                                    'name' => $list->user->name,
                                ],
                                'numberOfItems' => $list->entries_count,
                                'url' => route('lists.show', $list),
                            ],
                        ];
                    })->toArray(),
                ],
            ],
        ];

        $responseData = [
            'lists' => $lists,
            'metaTags' => $metaTags,
            'type' => $type,
            'search' => $search,
            'sort' => $sort,
            'filterGame' => $filterGame,
            'counts' => [
                'all' => $counts->all_count,
                'plan_to_read' => $counts->plan_to_read_count,
                'reading' => $counts->reading_count,
                'completed' => $counts->completed_count,
                'on_hold' => $counts->on_hold_count,
                'dropped' => $counts->dropped_count,
                'custom' => $counts->custom_count,
            ],
        ];

        // Cache the response for 1 hour (only non-search queries)
        if (empty($search)) {
            Cache::put($cacheKey, $responseData, now()->addHour());
        }

        return Inertia::render('lists/public', $responseData);
    }

    public function userPublicLists(Request $request, User $user): Response
    {
        $perPage = $request->input('per_page', 8);
        $search = $request->input('search', '');
        $types = $request->input('types', '');

        $query = VnList::withCount('entries')
            ->with([
                'user' => function ($q) {
                    $q->select('id', 'name', 'avatar');
                },
                'entries' => function ($query) {
                    $query->select('id', 'vn_list_id', 'game_id', 'sort_order')
                        ->with([
                            'game' => function ($q) {
                                $q->select('id', 'name', 'custom_name', 'has_custom_page', 'view_mode', 'thumb_url',
                                    'is_nsfw', 'slug', 'optimized_thumbnails', 'is_paid', 'has_demo', 'is_on_sale',
                                    'min_price');
                                // Explicitly prevent tags from being loaded
                                $q->without(['tags']);
                                $q->with([
                                    'latestVersion' => function ($vq) {
                                        $vq->select('id', 'game_id', 'version');
                                    },
                                ]);
                            },
                        ])
                        ->orderBy('sort_order')
                        ->limit(10); // Only load first 10 entries for card preview
                },
            ])
            ->where('user_id', $user->id)
            ->where('is_public', true)
            ->has('entries');

        // Apply search filter if provided
        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        // Apply type filters if provided
        if (! empty($types)) {
            $typeArray = explode(',', $types);
            $query->where(function ($q) use ($typeArray) {
                foreach ($typeArray as $type) {
                    if ($type === 'custom') {
                        $q->orWhereNotIn('type', ['plan_to_read', 'reading', 'completed', 'on_hold', 'dropped']);
                    } else {
                        $q->orWhere('type', $type);
                    }
                }
            });
        }

        // Sort by type priority first, then by creation date
        $lists = $query->orderByRaw("
            CASE type
                WHEN 'reading' THEN 1
                WHEN 'plan_to_read' THEN 2
                WHEN 'completed' THEN 3
                WHEN 'on_hold' THEN 4
                WHEN 'dropped' THEN 5
                ELSE 6
            END, created_at DESC
        ")->paginate($perPage);

        // Normalize game thumbnails to optimized URLs for client/preload
        $lists->getCollection()->each(function ($list) {
            $list->entries->each(function ($entry) {
                if ($entry->game) {
                    $optimized = $entry->game->getThumbnailUrl('default');
                    if ($optimized) {
                        $entry->game->setAttribute('thumb_url', $optimized);
                    }
                }
            });
        });

        $metaTags = [
            'title' => "{$user->name}'s Visual Novel Lists",
            'description' => "Browse {$user->name}'s public visual novel lists. ".
                "Currently featuring {$lists->total()} public lists".
                ($lists->isNotEmpty() ? ', including: '.$lists->take(3)->map(function ($list) {
                    return "{$list->name} (".$list->entries->count().' games)';
                })->implode(', ') : ''),
            'image' => ($lists->isNotEmpty() && $lists->first()->entries->isNotEmpty())
                ? ($lists->first()->entries->first()->game?->getThumbnailUrl('default') ?? asset(config('social.images.default')))
                : asset(config('social.images.default')),
            'structuredData' => [
                '@type' => 'ProfilePage',
                'name' => "{$user->name}'s Visual Novel Lists",
                'description' => "Browse {$user->name}'s public visual novel lists",
                'url' => route('lists.user-public', $user),
                'mainEntity' => [
                    '@type' => 'Person',
                    'name' => $user->name,
                ],
                'mainEntityOfPage' => [
                    '@type' => 'ItemList',
                    'name' => "{$user->name}'s Public Lists",
                    'numberOfItems' => $lists->total(),
                    'itemListElement' => $lists->take(3)->map(function ($list, $index) {
                        return [
                            '@type' => 'ListItem',
                            'position' => $index + 1,
                            'item' => [
                                '@type' => 'CreativeWork',
                                'name' => $list->name,
                                'description' => $list->description ?? 'A visual novel list',
                                'numberOfItems' => $list->entries_count,
                                'url' => route('lists.show', $list),
                            ],
                        ];
                    })->toArray(),
                ],
            ],
        ];

        return Inertia::render('lists/user-public', [
            'lists' => $lists,
            'user' => $user,
            'metaTags' => $metaTags,
        ]);
    }

    private function normalizePublicListType(mixed $type): string
    {
        return is_string($type) && in_array($type, self::PUBLIC_LIST_TYPES, true) ? $type : 'all';
    }

    private function normalizePublicListSort(mixed $sort): string
    {
        return is_string($sort) && in_array($sort, self::PUBLIC_LIST_SORTS, true) ? $sort : 'default';
    }

    private function normalizePublicListGameId(mixed $game): ?int
    {
        if ($game === null || $game === '') {
            return null;
        }

        if (! is_numeric($game)) {
            return null;
        }

        $gameId = (int) $game;

        return $gameId > 0 ? $gameId : null;
    }

    private function normalizePublicListPage(mixed $page): int
    {
        if (is_int($page)) {
            return max(1, $page);
        }

        if (! is_string($page) || ! ctype_digit($page)) {
            return 1;
        }

        return max(1, (int) $page);
    }

    private function normalizePublicListSearch(mixed $search): string
    {
        if (! is_string($search)) {
            return '';
        }

        $search = trim(preg_replace('/\s+/', ' ', $search) ?? '');
        if ($search === '') {
            return '';
        }

        $search = mb_substr($search, 0, self::PUBLIC_LISTS_MAX_SEARCH_LENGTH);
        $meaningfulSearch = preg_replace('/[%_\s]+/', '', $search) ?? '';

        return mb_strlen($meaningfulSearch) >= self::PUBLIC_LISTS_MIN_SEARCH_LENGTH ? $search : '';
    }
}
