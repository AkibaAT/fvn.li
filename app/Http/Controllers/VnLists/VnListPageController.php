<?php

declare(strict_types=1);

namespace App\Http\Controllers\VnLists;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\User;
use App\Models\VnList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class VnListPageController extends Controller
{
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

        $listOwnerId = $vnList->user_id;
        $vnList->load([
            'entries' => function ($query) use ($listOwnerId) {
                $query->with([
                    'game' => function ($q) use ($listOwnerId) {
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
                        // Load the list owner's review for each game
                        $q->with([
                            'ratings' => function ($rQuery) use ($listOwnerId) {
                                $rQuery->where('user_id', $listOwnerId)
                                    ->where('is_visible', true)
                                    ->select(['id', 'game_id', 'user_id', 'rating', 'is_reviewed']);
                            },
                        ]);
                    },
                ]);
                $query->orderBy('sort_order');
            }, 'user',
        ]);

        $allVersionIds = $vnList->entries->flatMap(function ($entry) {
            return $entry->game->gameVersions->pluck('id')
                ->merge($entry->game->latestVersion ? [$entry->game->latestVersion->id] : []);
        })->unique()->values()->toArray();

        $versionHasCharacterStats = [];
        if (! empty($allVersionIds)) {
            $statsVersionIds = DB::table('version_character_stats')
                ->whereIn('game_version_id', $allVersionIds)
                ->distinct()
                ->pluck('game_version_id')
                ->toArray();

            foreach ($allVersionIds as $vid) {
                $versionHasCharacterStats[$vid] = in_array($vid, $statsVersionIds);
            }
        }

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
            'versionHasCharacterStats' => $versionHasCharacterStats,
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
}
