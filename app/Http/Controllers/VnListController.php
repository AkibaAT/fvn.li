<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\User;
use App\Models\UserGameProgress;
use App\Models\VnList;
use App\Models\VnListEntry;
use App\Services\VnListCacheService;
use App\Traits\SortsVnLists;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class VnListController extends Controller
{
    use SortsVnLists;

    public function listsIndex(Request $request): Response
    {
        $authId = Auth::id();
        if (! $authId) {
            return Inertia::render('auth/login', [
                'metaTags' => ['title' => 'Log in'],
            ]);
        }
        $user = User::findOrFail($authId);

        $perPage = $request->input('per_page', 8);
        $visibility = $request->input('visibility', 'all');

        // Get counts for each tab with a single query using conditional aggregation
        $counts = VnList::where('user_id', $user->id)
            ->selectRaw('COUNT(*) as all_count')
            ->selectRaw('SUM(CASE WHEN is_public = true THEN 1 ELSE 0 END) as public_count')
            ->selectRaw('SUM(CASE WHEN is_public = false THEN 1 ELSE 0 END) as private_count')
            ->first();

        $listsQuery = VnList::withCount('entries')
            ->with([
                'entries' => function ($query) {
                    $query->select('id', 'vn_list_id', 'game_id', 'sort_order')
                        ->with([
                            'game' => function ($q) {
                                $q->select('id', 'name', 'custom_name', 'has_custom_page', 'view_mode', 'thumb_url',
                                    'is_nsfw', 'slug', 'optimized_thumbnails', 'is_paid', 'has_demo', 'is_on_sale',
                                    'min_price');
                                // Explicitly prevent tags from being loaded
                                $q->without(['tags']);
                                // Only load latestVersion if we actually need it for display
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
            ->where('user_id', $user->id);

        // Apply visibility filter if provided
        if ($visibility !== 'all') {
            if ($visibility === 'public') {
                $listsQuery->where('is_public', true);
            } elseif ($visibility === 'private') {
                $listsQuery->where('is_public', false);
            }
        }

        // Sort by type priority first, then by creation date
        $lists = $listsQuery->orderByRaw("
            CASE type
                WHEN 'reading' THEN 1
                WHEN 'plan_to_read' THEN 2
                WHEN 'completed' THEN 3
                WHEN 'on_hold' THEN 4
                WHEN 'dropped' THEN 5
                ELSE 6
            END, created_at DESC
        ")->paginate($perPage);

        // Ensure thumbnails prefer optimized versions in the serialized payload
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
            'title' => 'Your Visual Novel Lists',
            'description' => 'Manage your ' . ($visibility === 'all' ? '' : $visibility . ' ') .
                'visual novel lists. ' .
                "Currently managing {$lists->total()} lists" .
                ($lists->isNotEmpty() ? ', including: ' . $lists->take(3)->map(function ($list) {
                    return "{$list->name} (" . $list->entries->count() . ' games)';
                })->implode(', ') : ''),
            'structuredData' => [
                '@type' => 'WebPage',
                'name' => 'Your Visual Novel Lists',
                'description' => 'Manage your personal visual novel reading lists and track your progress',
                'url' => route('lists.index'),
                'mainEntity' => [
                    '@type' => 'ItemList',
                    'name' => 'Visual Novel Lists',
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
                            ],
                        ];
                    })->toArray(),
                ],
            ],
        ];

        return Inertia::render('lists/index', [
            'lists' => $lists,
            'visibility' => $visibility,
            'metaTags' => $metaTags,
            'counts' => [
                'all' => $counts->all_count,
                'public' => $counts->public_count,
                'private' => $counts->private_count,
            ],
        ]);
    }

    public function listShow(VnList $vnList): Response
    {
        $this->authorize('view', $vnList);

        $vnList->load([
            'entries' => function ($query) {
                $query->with([
                    'game' => function ($q) {
                        $q->select([
                            'id', 'name', 'custom_name', 'has_custom_page', 'view_mode', 'thumb_url', 'is_nsfw', 'slug',
                            'optimized_thumbnails', 'is_paid', 'has_demo', 'is_on_sale', 'min_price',
                        ]);
                        $q->with(['latestVersion', 'gameVersions']);
                        if (Auth::check()) {
                            $q->with([
                                'userProgress' => function ($upQuery) {
                                    $upQuery->where('user_id', Auth::id())
                                        ->with('gameVersion');
                                },
                            ]);
                        }
                    },
                ]);
                $query->orderBy('sort_order');
            }, 'user',
        ]);

        $isOwner = Auth::check() && Auth::id() === $vnList->user_id;

        $availableLists = [];
        if ($isOwner) {
            $availableLists = VnList::where('user_id', Auth::id())
                ->where('id', '!=', $vnList->id)
                ->select(['id', 'name', 'type'])
                ->get();
        }

        $metaTags = [
            'title' => $vnList->name . ' - Visual Novel List',
            'description' => $vnList->description ?:
                "A visual novel list by {$vnList->user->name} containing {$vnList->entries->count()} games.",
            'image' => asset(config('social.images.list_detail', config('social.images.default'))),
            'structuredData' => [
                '@type' => 'ItemList',
                'name' => $vnList->name,
                'description' => $vnList->description ?? "A visual novel list by {$vnList->user->name}",
                'url' => route('lists.show', $vnList),
                'author' => [
                    '@type' => 'Person',
                    'name' => $vnList->user->name,
                ],
                'numberOfItems' => $vnList->entries->count(),
                'itemListElement' => $vnList->entries->take(10)->map(function ($entry, $index) {
                    return [
                        '@type' => 'ListItem',
                        'position' => $index + 1,
                        'item' => [
                            '@type' => 'SoftwareApplication',
                            'name' => $entry->game->name,
                            'url' => route('games.show', $entry->game->slug),
                            'image' => $entry->game->getThumbnailUrl('default'),
                        ],
                    ];
                })->toArray(),
            ],
        ];

        return Inertia::render('lists/show', [
            'vnList' => $vnList,
            'isOwner' => $isOwner,
            'availableLists' => $availableLists,
            'metaTags' => $metaTags,
        ]);
    }

    public function listCreate(): Response
    {
        return Inertia::render('lists/create', [
            'metaTags' => [
                'title' => 'Create New Visual Novel List',
                'description' => 'Create a new custom visual novel list to organize and track your favorite games.',
                'structuredData' => [
                    '@type' => 'WebPage',
                    'name' => 'Create New Visual Novel List',
                    'description' => 'Create a new custom visual novel list to organize and track your favorite games.',
                    'url' => route('lists.create'),
                ],
            ],
        ]);
    }

    public function listEdit(VnList $vnList): Response
    {
        $this->authorize('update', $vnList);

        return Inertia::render('lists/edit', [
            'vnList' => $vnList,
            'metaTags' => [
                'title' => 'Edit List - ' . $vnList->name,
                'description' => 'Edit your visual novel list: ' . $vnList->name . '. Update the description, visibility, and manage your game entries.',
                'structuredData' => [
                    '@type' => 'WebPage',
                    'name' => 'Edit List - ' . $vnList->name,
                    'description' => 'Edit your visual novel list: ' . $vnList->name,
                    'url' => route('lists.edit', $vnList),
                ],
            ],
        ]);
    }

    public function publicLists(Request $request): Response
    {
        $perPage = $request->input('per_page', 8);
        $type = $request->input('type', 'all');
        $page = $request->input('page', 1);
        $search = $request->input('search', '');
        $sort = $request->input('sort', 'default'); // default, newest, oldest, most_entries
        $gameId = $request->input('game'); // Filter by game ID

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

        $lists = $query->paginate($perPage);

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
            'description' => 'Browse public visual novel lists shared by the community. ' .
                "Currently featuring {$lists->total()} public lists" .
                ($lists->isNotEmpty() ? ', including: ' . $lists->take(3)->map(function ($list) {
                    return "{$list->name} by {$list->user->name} (" . $list->entries->count() . ' games)';
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
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
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
            'description' => "Browse {$user->name}'s public visual novel lists. " .
                "Currently featuring {$lists->total()} public lists" .
                ($lists->isNotEmpty() ? ', including: ' . $lists->take(3)->map(function ($list) {
                    return "{$list->name} (" . $list->entries->count() . ' games)';
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

    public function storeVnList(\App\Http\Requests\StoreVnListRequest $request): JsonResponse
    {

        $isPublic = $request->boolean('is_public', false);
        $vnList = VnList::create([
            'user_id' => Auth::id(),
            'name' => $request->name,
            'description' => $request->description,
            'type' => 'custom', // All user-created lists are custom type
            'is_public' => $isPublic,
            'is_default' => false, // User-created lists are never default
        ]);

        // If a game_id is provided, add the game to the new list
        if ($request->has('game_id')) {
            VnListEntry::create([
                'vn_list_id' => $vnList->id,
                'game_id' => $request->game_id,
                'sort_order' => 10,
            ]);
        }

        // Clear cache if this is a public list
        if ($isPublic) {
            app(VnListCacheService::class)->clearPublicListsCache();
        }

        return response()->json([
            'success' => true,
            'message' => $request->has('game_id')
                ? 'List created and game added successfully.'
                : 'List created successfully.',
            'list' => $vnList,
        ]);
    }

    public function updateVnList(\App\Http\Requests\UpdateVnListRequest $request, VnList $vnList): JsonResponse
    {
        $this->authorize('update', $vnList);

        // Note: List type cannot be changed after creation
        // System lists (is_default=true) have fixed types
        // User lists are always 'custom' type

        $wasPublic = $vnList->is_public;
        $vnList->update([
            'name' => $request->name,
            'description' => $request->description,
            'is_public' => $request->boolean('is_public', $vnList->is_public),
        ]);

        // Clear cache if this is a public list or was public before
        if ($wasPublic || $vnList->is_public) {
            app(VnListCacheService::class)->clearPublicListsCache();
        }

        return response()->json([
            'success' => true,
            'message' => 'List updated successfully.',
            'vnList' => $vnList->fresh(),
        ]);
    }

    public function destroyVnList(VnList $vnList): JsonResponse
    {
        $this->authorize('delete', $vnList);

        if ($vnList->is_default) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete default lists.',
            ], 422);
        }

        // Check if this is a public list before deleting
        $isPublic = $vnList->is_public;
        $vnList->delete();

        // Clear cache if this was a public list
        if ($isPublic) {
            app(VnListCacheService::class)->clearPublicListsCache();
        }

        return response()->json([
            'success' => true,
            'message' => 'List deleted successfully.',
        ]);
    }

    public function toggleVnListVisibility(VnList $vnList): JsonResponse
    {
        $this->authorize('update', $vnList);

        $vnList->update(['is_public' => ! $vnList->is_public]);
        $status = $vnList->is_public ? 'public' : 'private';

        // Clear cache since this list's visibility has changed
        app(VnListCacheService::class)->clearPublicListsCache();

        return response()->json([
            'success' => true,
            'message' => "List is now {$status}.",
            'is_public' => $vnList->is_public,
        ]);
    }

    public function toggleAllUpdates(\App\Http\Requests\ToggleAllUpdatesRequest $request, VnList $vnList): JsonResponse
    {
        $this->authorize('update', $vnList);

        $freeGameIds = $vnList->entries()
            ->whereHas('game', function ($query) {
                $query->where('is_paid', false);
            })
            ->pluck('game_id')
            ->toArray();

        if (empty($freeGameIds)) {
            return response()->json([
                'success' => false,
                'message' => 'No free games found in this list.',
            ], 422);
        }

        $receiveUpdates = $request->boolean('receive_updates');

        // Update all free games' notification settings
        UserGameProgress::where('user_id', Auth::id())
            ->whereIn('game_id', $freeGameIds)
            ->update(['receive_updates' => $receiveUpdates]);

        // Also create records for games that don't have user_game_progress yet
        $existingGameIds = UserGameProgress::where('user_id', Auth::id())
            ->whereIn('game_id', $freeGameIds)
            ->pluck('game_id')
            ->toArray();

        $missingGameIds = array_diff($freeGameIds, $existingGameIds);

        if (! empty($missingGameIds)) {
            $insertData = array_map(function ($gameId) use ($receiveUpdates) {
                return [
                    'user_id' => Auth::id(),
                    'game_id' => $gameId,
                    'receive_updates' => $receiveUpdates,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }, $missingGameIds);

            UserGameProgress::insert($insertData);
        }

        $status = $receiveUpdates ? 'enabled' : 'disabled';

        // Clear cache if this is a public list
        if ($vnList->is_public) {
            app(VnListCacheService::class)->clearPublicListsCache();
        }

        return response()->json([
            'success' => true,
            'message' => "Notifications {$status} for all free games in this list.",
            'updated_game_ids' => $freeGameIds,
            'receive_updates' => $receiveUpdates,
        ]);
    }

    public function addGameToList(\App\Http\Requests\AddGameToListRequest $request, Game $game): JsonResponse
    {

        // Handle both list_id and list_type parameters
        if ($request->has('list_id')) {
            $vnList = VnList::findOrFail($request->list_id);
        } elseif ($request->has('list_type')) {
            // Find the user's default list of the specified type
            $vnList = VnList::where('user_id', Auth::id())
                ->where('type', $request->list_type)
                ->where('is_default', true)
                ->first();

            if (! $vnList) {
                return response()->json([
                    'success' => false,
                    'message' => 'Default list not found for type: ' . $request->list_type,
                ], 404);
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Either list_id or list_type must be provided.',
            ], 422);
        }

        $this->authorize('update', $vnList);

        $existingEntry = VnListEntry::where('vn_list_id', $vnList->id)
            ->where('game_id', $game->id)
            ->first();

        // For default lists, toggle behavior: remove if exists, add if doesn't exist
        if ($request->has('list_type') && $existingEntry) {
            // Remove from list (toggle off)
            $existingEntry->delete();

            // Clear cache if this is a public list
            if ($vnList->is_public) {
                app(VnListCacheService::class)->clearPublicListsCache();
            }

            return response()->json([
                'success' => true,
                'message' => "Game removed from {$vnList->name} successfully.",
                'action' => 'removed',
            ]);
        }

        // For regular list_id requests, don't allow duplicates
        if ($request->has('list_id') && $existingEntry) {
            return response()->json([
                'success' => false,
                'message' => 'Game is already in this list.',
            ], 422);
        }

        // For default lists with list_type, remove from other default lists first
        if ($request->has('list_type')) {
            // Remove game from all other default lists for this user
            $otherDefaultLists = VnList::where('user_id', Auth::id())
                ->where('is_default', true)
                ->where('type', '!=', $request->list_type)
                ->whereIn('type', ['reading', 'completed', 'plan_to_read', 'on_hold', 'dropped'])
                ->pluck('id');

            VnListEntry::whereIn('vn_list_id', $otherDefaultLists)
                ->where('game_id', $game->id)
                ->delete();
        }

        // Add to the specified list
        $entry = VnListEntry::create([
            'vn_list_id' => $vnList->id,
            'game_id' => $game->id,
            'sort_order' => ($vnList->entries()->max('sort_order') ?? 0) + 10,
        ]);

        // Clear cache if this is a public list
        if ($vnList->is_public) {
            app(VnListCacheService::class)->clearPublicListsCache();
        }

        return response()->json([
            'success' => true,
            'message' => "Game added to {$vnList->name} successfully.",
            'action' => 'added',
            'entry' => $entry->load('game'),
        ]);
    }

    public function addGameToCustomList(\App\Http\Requests\AddGameToCustomListRequest $request, VnList $vnList): JsonResponse
    {
        $this->authorize('update', $vnList);

        $game = Game::findOrFail($request->game_id);

        $existingEntry = VnListEntry::where('vn_list_id', $vnList->id)
            ->where('game_id', $game->id)
            ->first();

        // Toggle behavior: remove if exists, add if doesn't exist
        if ($existingEntry) {
            $existingEntry->delete();

            // Clear cache if this is a public list
            if ($vnList->is_public) {
                app(VnListCacheService::class)->clearPublicListsCache();
            }

            return response()->json([
                'success' => true,
                'message' => "Game removed from {$vnList->name} successfully.",
                'action' => 'removed',
            ]);
        }

        $entry = VnListEntry::create([
            'vn_list_id' => $vnList->id,
            'game_id' => $game->id,
            'sort_order' => ($vnList->entries()->max('sort_order') ?? 0) + 10,
        ]);

        // Clear cache if this is a public list
        if ($vnList->is_public) {
            app(VnListCacheService::class)->clearPublicListsCache();
        }

        return response()->json([
            'success' => true,
            'message' => "Game added to {$vnList->name} successfully.",
            'action' => 'added',
            'entry' => $entry->load('game'),
        ]);
    }

    public function updateUserProgress(\App\Http\Requests\UpdateUserProgressRequest $request, Game $game): JsonResponse
    {

        $progress = UserGameProgress::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'game_id' => $game->id,
            ],
            [
                'game_version_id' => $request->game_version_id ?: null,
                'personal_notes' => $request->personal_notes ?: null,
                'started_at' => $request->started_at ?: null,
                'completed_at' => $request->completed_at ?: null,
            ]
        );

        // Clear cache if this game is in any public lists
        $publicListsContainingGame = VnList::where('is_public', true)
            ->whereHas('entries', function ($query) use ($game) {
                $query->where('game_id', $game->id);
            })
            ->exists();

        if ($publicListsContainingGame) {
            app(VnListCacheService::class)->clearPublicListsCache();
        }

        return response()->json([
            'success' => true,
            'message' => 'Progress updated successfully.',
            'progress' => $progress->fresh(['gameVersion']),
        ]);
    }

    public function toggleUserProgressUpdates(\App\Http\Requests\ToggleUserProgressUpdatesRequest $request, int $game): JsonResponse
    {

        $receiveUpdates = $request->boolean('receive_updates');

        // Use a direct update/insert query for maximum performance
        // This will be a single query instead of multiple
        UserGameProgress::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'game_id' => $game,
            ],
            [
                'receive_updates' => $receiveUpdates,
            ]
        );

        $status = $receiveUpdates ? 'enabled' : 'disabled';

        return response()->json([
            'success' => true,
            'message' => "Notifications {$status}.",
            'receive_updates' => $receiveUpdates,
        ]);
    }

    public function getUserProgressStatus(Game $game): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        // Get the user's progress for this game
        $progress = UserGameProgress::where('user_id', $authId)
            ->where('game_id', $game->id)
            ->first();

        return response()->json([
            'success' => true,
            'receive_updates' => $progress ? $progress->receive_updates : false,
        ]);
    }

    public function getUserLists(): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }
        $user = User::findOrFail($authId);

        $baseQuery = method_exists($user, 'vnLists') ? $user->vnLists() : VnList::where('user_id', $user->id);
        $lists = $baseQuery
            ->select('id', 'name', 'type', 'is_default')
            ->orderBy('created_at')
            ->get();

        $lists = $this->sortListsByType($lists);

        return response()->json([
            'success' => true,
            'lists' => $lists,
        ]);
    }

    public function getGameLists(Game $game): JsonResponse
    {
        $authId = Auth::id();
        if (! $authId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        // Get all list IDs that contain this game for the authenticated user
        $listIds = VnListEntry::whereHas('list', function ($query) use ($authId) {
            $query->where('user_id', $authId);
        })
            ->where('game_id', $game->id)
            ->pluck('vn_list_id')
            ->toArray();

        return response()->json([
            'success' => true,
            'list_ids' => $listIds,
        ]);
    }

    public function updateListEntry(\App\Http\Requests\UpdateListEntryRequest $request, VnListEntry $entry): JsonResponse
    {
        $this->authorize('update', $entry->list);

        $progress = UserGameProgress::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'game_id' => $entry->game_id,
            ],
            [
                'game_version_id' => $request->game_version_id ?: null,
                'personal_notes' => $request->personal_notes ?: null,
                'started_at' => $request->started_at ?: null,
                'completed_at' => $request->completed_at ?: null,
            ]
        );

        $entry->update([
            'private_notes' => $request->private_notes,
        ]);

        // Clear cache if this is a public list
        if ($entry->list->is_public) {
            app(VnListCacheService::class)->clearPublicListsCache();
        }

        return response()->json([
            'success' => true,
            'message' => 'Entry updated successfully.',
            'progress' => $progress->fresh(['gameVersion']),
            'entry' => $entry->fresh(),
        ]);
    }

    public function moveListEntry(\App\Http\Requests\MoveListEntryRequest $request, VnListEntry $entry): JsonResponse
    {
        $this->authorize('update', $entry->list);

        $targetList = VnList::findOrFail($request->target_list_id);
        $this->authorize('update', $targetList);

        $existingEntry = VnListEntry::where('vn_list_id', $targetList->id)
            ->where('game_id', $entry->game_id)
            ->first();

        if ($existingEntry) {
            return response()->json([
                'success' => false,
                'message' => 'Game is already in the target list.',
            ], 422);
        }

        // Check if either list is public before moving
        $sourceIsPublic = $entry->list->is_public;
        $targetIsPublic = $targetList->is_public;

        $entry->update(['vn_list_id' => $targetList->id]);

        // Clear cache if either list is public
        if ($sourceIsPublic || $targetIsPublic) {
            app(VnListCacheService::class)->clearPublicListsCache();
        }

        return response()->json([
            'success' => true,
            'message' => 'Game moved successfully.',
            'target_list' => $targetList,
        ]);
    }

    public function removeListEntry(VnListEntry $entry): JsonResponse
    {
        $this->authorize('update', $entry->list);

        // Check if this is a public list before deleting
        $isPublic = $entry->list->is_public;
        $entry->delete();

        // Clear cache if this was a public list
        if ($isPublic) {
            app(VnListCacheService::class)->clearPublicListsCache();
        }

        return response()->json([
            'success' => true,
            'message' => 'Game removed from list.',
        ]);
    }

    public function reorderListEntries(\App\Http\Requests\ReorderListEntriesRequest $request, VnList $vnList): JsonResponse
    {
        $this->authorize('update', $vnList);

        // Get all valid entry IDs for this list in one query for security check
        $validEntryIds = VnListEntry::where('vn_list_id', $vnList->id)
            ->pluck('id')
            ->toArray();

        // Filter out any invalid entry IDs
        $entryIds = array_intersect($request->entry_ids, $validEntryIds);

        if (count($entryIds) !== count($request->entry_ids)) {
            return response()->json([
                'success' => false,
                'message' => 'Some entry IDs are invalid for this list.',
            ], 422);
        }

        // Use a single query to update all entries at once
        $cases = [];
        $ids = [];
        foreach ($entryIds as $index => $entryId) {
            $sortOrder = ($index + 1) * 10;
            $cases[] = "WHEN id = {$entryId} THEN {$sortOrder}";
            $ids[] = $entryId;
        }

        if (! empty($cases)) {
            $casesString = implode(' ', $cases);
            $idsString = implode(',', $ids);

            DB::statement("
                UPDATE vn_list_entries
                SET sort_order = CASE {$casesString} END
                WHERE id IN ({$idsString}) AND vn_list_id = {$vnList->id}
            ");
        }

        // Clear cache if this is a public list
        if ($vnList->is_public) {
            app(VnListCacheService::class)->clearPublicListsCache();
        }

        return response()->json([
            'success' => true,
            'message' => 'List order updated.',
        ]);
    }

    /**
     * Clear the public lists cache
     */
    private function clearPublicListsCache(): void
    {
        app(VnListCacheService::class)->clearPublicListsCache();
    }
}
