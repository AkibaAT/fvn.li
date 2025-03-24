<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\User;
use App\Models\UserGameProgress;
use App\Models\VnList;
use App\Models\VnListEntry;
use App\Traits\SortsVnLists;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VnListController extends Controller
{
    use SortsVnLists;

    /**
     * Display the user's VN lists.
     *
     * @param  Request  $request  The incoming request
     */
    public function index(Request $request): View
    {
        $user = Auth::user();

        // Filter by visibility if requested
        $listsQuery = $user->vnLists();

        if ($request->has('visibility')) {
            if ($request->visibility === 'public') {
                $listsQuery->where('is_public', true);
            } elseif ($request->visibility === 'private') {
                $listsQuery->where('is_public', false);
            }
        }

        // Order lists by creation time (latest first)
        $listsQuery->latest();

        $lists = $listsQuery->with(['entries.game'])->get();

        // Sort lists by type and custom lists alphabetically
        $lists = $this->sortListsByType($lists);

        // Generate metadata for the user's lists page
        $visibility = $request->visibility ?? 'all';
        $metaTags = [
            'title' => 'Your Visual Novel Lists - ' . config('app.name'),
            'description' => 'Manage your ' . ($visibility === 'all' ? '' : $visibility . ' ') .
                'visual novel lists. ' .
                "Currently managing {$lists->count()} lists" .
                ($lists->isNotEmpty() ? ', including: ' . $lists->take(3)->map(function ($list) {
                    return "{$list->name} (" . $list->entries->count() . ' games)';
                })->implode(', ') : ''),
            'image' => $lists->isNotEmpty() && $lists->first()->entries->isNotEmpty() &&
                $lists->first()->entries->first()->game->thumb_url ?
                $lists->first()->entries->first()->game->thumb_url : '',
        ];

        return view('lists.user.index', ['lists' => $lists, 'metaTags' => $metaTags]);
    }

    /**
     * Show the form for creating a new list.
     */
    public function create(): View
    {
        $metaTags = [
            'title' => 'Create New Visual Novel List - ' . config('app.name'),
            'description' => 'Create a new visual novel list to organize and share your favorite visual novels.',
            'noindex' => true, // We don't want search engines to index the create form
        ];

        return view('lists.user.create', ['metaTags' => $metaTags]);
    }

    /**
     * Store a newly created list.
     *
     * @param  Request  $request  The incoming request
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('vn_lists')->where(function ($query) {
                return $query->where('user_id', auth()->id());
            })],
            'game_id' => 'nullable|exists:games,id',
            'description' => 'nullable|string',
            'is_public' => 'nullable',
        ]);

        $list = VnList::create([
            'name' => $request->name,
            'description' => $request->description,
            'user_id' => auth()->id(),
            'is_default' => false,
            'is_public' => $request->has('is_public'),
            'type' => 'custom',
        ]);

        if ($request->game_id) {
            // Add the game to the new list
            $entry = VnListEntry::create([
                'vn_list_id' => $list->id,
                'game_id' => $request->game_id,
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'List created and game added successfully',
                    'list' => [
                        'id' => $list->id,
                        'name' => $list->name,
                    ],
                    'entry_id' => $entry->id,
                ]);
            }

            return redirect()->route('vn-lists.show', $list)
                ->with('success', 'List created and game added successfully');
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'List created successfully',
                'list' => [
                    'id' => $list->id,
                    'name' => $list->name,
                ],
            ]);
        }

        return redirect()->route('vn-lists.show', $list)
            ->with('success', 'List created successfully');
    }

    /**
     * Display the specified list.
     *
     * @param  VnList  $vnList  The list to display
     */
    public function show(VnList $vnList): View
    {
        $this->authorize('view', $vnList);

        // Eager load all the relationships we need
        $vnList->load(['entries' => function ($query) {
            $query->with(['game' => function ($q) {
                $q->select('id', 'name', 'thumb_url', 'is_nsfw', 'slug', 'optimized_thumbnails');
                $q->with(['latestVersion']);
            }]);
            $query->orderBy('sort_order');
        }, 'user']);

        $isOwner = auth()->check() && auth()->id() === $vnList->user_id;

        // Generate metadata for the list
        $listType = str_replace('_', ' ', $vnList->type);
        $metaTags = [
            'title' => $vnList->name . ($isOwner ? '' : " by {$vnList->user->name}") . ' - ' . config('app.name'),
            'description' => ($isOwner ? 'Your' : "{$vnList->user->name}'s") . ' ' . $listType . ' list' .
                ($vnList->description ? ": {$vnList->description}" : '') .
                ". Contains {$vnList->entries->count()} visual novels" .
                ($vnList->entries->isNotEmpty() ? ', including: ' . $vnList->entries->take(4)->map(function ($entry) {
                    return $entry->game->name;
                })->implode(', ') : ''),
            'image' => $vnList->entries->isNotEmpty() && $vnList->entries->first()->game->thumb_url ?
                $vnList->entries->first()->game->thumb_url : '',
        ];

        return view('lists.user.show', [
            'vnList' => $vnList,
            'isOwner' => $isOwner,
            'metaTags' => $metaTags,
        ]);
    }

    /**
     * Show the form for editing a list.
     *
     * @param  VnList  $vnList  The list to edit
     */
    public function edit(VnList $vnList): View
    {
        $this->authorize('update', $vnList);

        $metaTags = [
            'title' => 'Edit ' . $vnList->name . ' - ' . config('app.name'),
            'description' => 'Edit your ' . str_replace('_', ' ', $vnList->type) . ' list "' . $vnList->name . '".',
            'noindex' => true, // We don't want search engines to index the edit form
        ];

        return view('lists.user.edit', ['vnList' => $vnList, 'metaTags' => $metaTags]);
    }

    /**
     * Update the specified list.
     *
     * @param  Request  $request  The incoming request
     * @param  VnList  $vnList  The list to update
     */
    public function update(Request $request, VnList $vnList): RedirectResponse
    {
        $this->authorize('update', $vnList);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('vn_lists')->where(function ($query) use ($vnList) {
                return $query->where('user_id', auth()->id())
                    ->where('id', '!=', $vnList->id);
            })],
            'description' => ['nullable', 'string'],
            'is_public' => ['sometimes', 'boolean'],
        ]);

        // Always preserve the original type of the list
        $validated['type'] = $vnList->type;

        $validated['is_public'] = $request->has('is_public');

        $vnList->update($validated);

        return redirect()->route('vn-lists.index')
            ->with('success', 'List updated successfully.');
    }

    /**
     * Remove the specified list.
     *
     * @param  VnList  $vnList  The list to delete
     */
    public function destroy(VnList $vnList): RedirectResponse
    {
        $this->authorize('delete', $vnList);

        // Don't allow deleting default lists
        if ($vnList->is_default) {
            return back()->with('error', 'Default lists cannot be deleted.');
        }

        $vnList->delete();

        return redirect()->route('vn-lists.index')->with('success', 'List deleted successfully.');
    }

    /**
     * Add a game to a default list type.
     *
     * @param  Request  $request  The incoming request
     * @param  Game  $game  The game to add
     */
    public function addGame(Request $request, Game $game): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'list_type' => ['required', Rule::in(['reading', 'completed', 'plan_to_read', 'on_hold', 'dropped', 'custom'])],
            'list_id' => ['nullable', 'exists:vn_lists,id'],
        ]);

        // If list_type is 'custom', a list_id must be provided
        if ($validated['list_type'] === 'custom' && empty($validated['list_id'])) {
            return back()->with('error', 'A specific list must be selected when adding to a custom list.');
        }

        // Determine which list to add to
        if ($validated['list_type'] === 'custom' && ! empty($validated['list_id'])) {
            $list = VnList::findOrFail($validated['list_id']);
            // Ensure the list belongs to the user
            if ($list->user_id !== Auth::id()) {
                return back()->with('error', 'You can only add games to your own lists.');
            }
        } else {
            // Use default list of the selected type
            $list = VnList::where('user_id', Auth::id())
                ->where('is_default', true)
                ->where('type', $validated['list_type'])
                ->first();

            if (! $list) {
                return back()->with('error', 'Default list not found for the selected type.');
            }
        }

        // Check if the game is already in any of the user's lists
        $existingEntryInOtherList = VnListEntry::whereHas('list', function ($query) {
            $query->where('user_id', Auth::id());
        })
            ->where('game_id', $game->id)
            ->first();

        // If the game is already in a list, remove it from that list first
        if ($existingEntryInOtherList) {
            // If it's already in the target list, just return
            if ($existingEntryInOtherList->vn_list_id === $list->id) {
                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Game is already in this list.',
                    ]);
                }

                return back()->with('info', 'Game is already in this list.');
            }

            $existingEntryInOtherList->delete();
        }

        // Prepare data for the new entry
        $entryData = [
            'vn_list_id' => $list->id,
            'game_id' => $game->id,
        ];

        // Get the highest sort_order in the target list
        $maxSortOrder = $list->entries()->max('sort_order') ?? 0;
        $entryData['sort_order'] = $maxSortOrder + 10;

        // Get or initialize user progress for this game
        $userProgress = UserGameProgress::firstOrNew([
            'user_id' => Auth::id(),
            'game_id' => $game->id,
        ]);

        // Set timestamps based on list type according to the specified rules
        if ($validated['list_type'] === 'reading') {
            // For "Currently Reading" list, set started_at if it's currently null
            if ($userProgress->started_at === null) {
                $userProgress->started_at = now();
            }
        } elseif ($validated['list_type'] === 'completed') {
            // For "Completed" list, set completed_at if it's currently null
            if ($userProgress->completed_at === null) {
                $userProgress->completed_at = now();
            }
        }

        // Update status based on list type
        $userProgress->status = $validated['list_type'];
        $userProgress->save();

        $entry = $list->entries()->create($entryData);

        $message = $existingEntryInOtherList
            ? 'Game moved to your ' . strtolower(str_replace('_', ' ', $list->type)) . ' list.'
            : 'Game added to your ' . strtolower(str_replace('_', ' ', $list->type)) . ' list.';

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'entryId' => $entry->id,
                'is_public' => $list->is_public,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Update a list entry.
     *
     * @param  Request  $request  The incoming request
     * @param  VnListEntry  $entry  The entry to update
     */
    public function updateEntry(Request $request, VnListEntry $entry): JsonResponse
    {
        $this->authorize('update', $entry->list);

        $validationRules = [
            'game_version_id' => ['nullable', 'exists:game_versions,id'],
            'notes' => ['nullable', 'string'],
            'started_at' => ['nullable', 'date'],
        ];

        // Only allow completed_at for custom and completed lists
        if ($entry->list->type === 'custom' || $entry->list->type === 'completed') {
            $validationRules['completed_at'] = ['nullable', 'date'];
        }

        $validated = $request->validate($validationRules);

        // If game_version_id is set, verify it belongs to the correct game
        if (! empty($validated['game_version_id'])) {
            $gameVersion = GameVersion::findOrFail($validated['game_version_id']);
            if ($gameVersion->game_id !== $entry->game_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid game version selected.',
                ], 422);
            }
        }

        // For non-custom/completed lists, ensure completed_at is not updated
        if (! ($entry->list->type === 'custom' || $entry->list->type === 'completed')) {
            unset($validated['completed_at']);
        }

        $entry->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Entry updated successfully.',
        ]);
    }

    /**
     * Move a game to a different list.
     *
     * @param  Request  $request  The incoming request
     * @param  VnListEntry  $entry  The entry to move
     */
    public function moveGame(Request $request, VnListEntry $entry): JsonResponse
    {
        $this->authorize('update', $entry->list);

        $validated = $request->validate([
            'target_list_id' => ['required', 'exists:vn_lists,id'],
        ]);

        $targetList = VnList::findOrFail($validated['target_list_id']);

        // Check if the target list belongs to the user
        $this->authorize('update', $targetList);

        // Check if there are any valid lists to move to
        $listsWithThisGame = VnList::where('user_id', auth()->id())
            ->whereHas('entries', function ($query) use ($entry) {
                $query->where('game_id', $entry->game_id);
            })
            ->pluck('id')
            ->toArray();

        $totalUserLists = auth()->user()->vnLists()->count();

        if (count($listsWithThisGame) >= $totalUserLists - 1) {
            return response()->json([
                'success' => false,
                'message' => 'This VN is already in all your other lists.',
            ], 400);
        }

        // Check if the game is already in the target list
        $existingEntry = VnListEntry::where('vn_list_id', $targetList->id)
            ->where('game_id', $entry->game_id)
            ->first();

        if ($existingEntry) {
            return response()->json([
                'success' => false,
                'message' => 'Game is already in the target list.',
            ], 400);
        }

        // Get the highest sort_order in the target list
        $maxSortOrder = VnListEntry::where('vn_list_id', $targetList->id)
            ->max('sort_order') ?? 0;

        // Move the entry to the new list and update its sort order
        $entry->update([
            'vn_list_id' => $targetList->id,
            'sort_order' => $maxSortOrder + 10,
        ]);

        // Get user progress for this game or create a new one if it doesn't exist
        $userProgress = UserGameProgress::firstOrNew([
            'user_id' => auth()->id(),
            'game_id' => $entry->game_id,
        ]);

        // Update timestamps based on target list type according to the specified rules
        if ($targetList->type === 'reading') {
            // For "Currently Reading" list, set started_at if it's currently null
            if ($userProgress->started_at === null) {
                $userProgress->started_at = now();
            }
        } elseif ($targetList->type === 'completed') {
            // For "Completed" list, set completed_at if it's currently null
            if ($userProgress->completed_at === null) {
                $userProgress->completed_at = now();
            }
        }

        // Update status based on target list type
        $userProgress->status = $targetList->type;
        $userProgress->save();

        return response()->json([
            'success' => true,
            'message' => 'Game moved to ' . $targetList->name . ' list.',
        ]);
    }

    /**
     * Remove a game from a list.
     *
     * @param  VnListEntry  $entry  The entry to remove
     */
    public function removeGame(VnListEntry $entry): JsonResponse
    {
        $this->authorize('update', $entry->list);

        $entry->delete();

        return response()->json([
            'success' => true,
            'message' => 'Game removed from list.',
        ]);
    }

    /**
     * Display all public lists.
     *
     * @param  Request  $request  The incoming request
     */
    public function publicLists(Request $request): View
    {
        // Eager load all the relationships we need
        $lists = VnList::with(['user', 'entries' => function ($query) {
            $query->with(['game' => function ($q) {
                $q->select('id', 'name', 'thumb_url', 'is_nsfw', 'slug', 'optimized_thumbnails');
                $q->with(['latestVersion']);
            }]);
            $query->orderBy('sort_order');
        }])
            ->where('is_public', true)
            ->latest()
            ->paginate(9);

        $lists = $this->sortListsByType($lists);

        // Generate metadata for public lists page
        $metaTags = [
            'title' => 'Public Visual Novel Lists - ' . config('app.name'),
            'description' => 'Browse public visual novel lists shared by the community. ' .
                "Currently featuring {$lists->total()} public lists" .
                ($lists->isNotEmpty() ? ', including: ' . $lists->take(3)->map(function ($list) {
                    return "{$list->name} by {$list->user->name} (" . $list->entries->count() . ' games)';
                })->implode(', ') : ''),
            'image' => $lists->isNotEmpty() && $lists->first()->entries->isNotEmpty() &&
                $lists->first()->entries->first()->game->thumb_url ?
                $lists->first()->entries->first()->game->thumb_url : '',
        ];

        return view('lists.public.index', [
            'lists' => $lists,
            'metaTags' => $metaTags,
        ]);
    }

    /**
     * Display a user's public lists.
     *
     * @param  Request  $request  The incoming request
     * @param  User  $user  The user whose lists to display
     */
    public function userPublicLists(Request $request, User $user): View
    {
        $lists = VnList::with(['user', 'entries' => function ($query) {
            $query->with(['game' => function ($q) {
                $q->select('id', 'name', 'thumb_url', 'is_nsfw', 'slug', 'optimized_thumbnails');
                $q->with(['latestVersion']);
            }]);
            $query->orderBy('sort_order');
        }])
            ->where('user_id', $user->id)
            ->where('is_public', true)
            ->latest()
            ->paginate(9);

        $lists = $this->sortListsByType($lists);

        // Generate metadata for user's public lists page
        $metaTags = [
            'title' => "{$user->name}'s Visual Novel Lists - " . config('app.name'),
            'description' => "Browse {$user->name}'s public visual novel lists. " .
                "Currently featuring {$lists->total()} public lists" .
                ($lists->isNotEmpty() ? ', including: ' . $lists->take(3)->map(function ($list) {
                    return "{$list->name} (" . $list->entries->count() . ' games)';
                })->implode(', ') : ''),
            'image' => $lists->isNotEmpty() && $lists->first()->entries->isNotEmpty() &&
                $lists->first()->entries->first()->game->thumb_url ?
                $lists->first()->entries->first()->game->thumb_url : '',
        ];

        return view('lists.public.user', [
            'lists' => $lists,
            'user' => $user,
            'metaTags' => $metaTags,
        ]);
    }

    /**
     * Toggle a list's visibility.
     *
     * @param  VnList  $vnList  The list to toggle
     */
    public function toggleVisibility(VnList $vnList): JsonResponse
    {
        $this->authorize('update', $vnList);

        $vnList->update(['is_public' => ! $vnList->is_public]);

        $status = $vnList->is_public ? 'public' : 'private';

        return response()->json([
            'success' => true,
            'message' => "List is now {$status}.",
        ]);
    }

    /**
     * Add a game to a custom list.
     *
     * @param  Request  $request  The incoming request
     * @param  VnList  $vnList  The list to add to
     */
    public function addToCustomList(Request $request, VnList $vnList): JsonResponse
    {
        $this->authorize('update', $vnList);

        // Validate the game_id exists
        $validated = $request->validate([
            'game_id' => ['required', 'exists:games,id'],
        ]);

        // Check if the game is already in this list
        $existingEntry = $vnList->entries()->where('game_id', $validated['game_id'])->first();

        if ($existingEntry) {
            // If the game is already in the list, remove it
            $existingEntry->delete();

            return response()->json([
                'success' => true,
                'message' => 'Game removed from "' . $vnList->name . '" list.',
            ]);
        }

        // Check if the game is in any other custom list
        $existingEntryInOtherList = VnListEntry::whereIn(
            'vn_list_id',
            VnList::where('user_id', auth()->id())
                ->where('is_default', false)
                ->where('id', '!=', $vnList->id)
                ->pluck('id')
        )
            ->where('game_id', $validated['game_id'])
            ->first();

        // Get the highest sort_order in the list
        $maxSortOrder = $vnList->entries()->max('sort_order') ?? 0;

        // Add the game to the list
        $entry = $vnList->entries()->create([
            'game_id' => $validated['game_id'],
            'sort_order' => $maxSortOrder + 10,
        ]);

        // Get or initialize user progress for this game
        $userProgress = UserGameProgress::firstOrNew([
            'user_id' => auth()->id(),
            'game_id' => $validated['game_id'],
        ]);

        // For custom lists, don't automatically set any date fields
        // Now we can use 'custom' as a valid status value
        $userProgress->status = 'custom';
        $userProgress->save();

        $message = $existingEntryInOtherList
            ? 'Game moved to "' . $vnList->name . '" list.'
            : 'Game added to "' . $vnList->name . '" list.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'entry_id' => $entry->id,
        ]);
    }

    /**
     * Update the order of games in a list.
     *
     * @param  Request  $request  The incoming request
     * @param  VnList  $vnList  The list to update
     */
    public function updateOrder(Request $request, VnList $vnList): JsonResponse
    {
        $this->authorize('update', $vnList);

        $validated = $request->validate([
            'entries' => ['required', 'array'],
            'entries.*' => ['required', 'integer', 'exists:vn_list_entries,id'],
        ]);

        // Verify all entries belong to this list
        $entries = VnListEntry::whereIn('id', $validated['entries'])
            ->where('vn_list_id', $vnList->id)
            ->get();

        if ($entries->count() !== count($validated['entries'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid entries provided.',
            ], 422);
        }

        // Update sort order for each entry
        foreach ($validated['entries'] as $index => $entryId) {
            VnListEntry::where('id', $entryId)->update([
                'sort_order' => ($index + 1) * 10,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order updated successfully.',
        ]);
    }

    /**
     * Toggle update notifications for a list entry.
     *
     * @param  Request  $request  The incoming request
     * @param  VnListEntry  $entry  The entry to update
     */
    public function toggleUpdates(Request $request, VnListEntry $entry): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $entry->list);

        // Determine if updates should be received based on the checkbox state
        $receiveUpdates = false;

        // Check if the checkbox is checked (value is '1' or true)
        if ($request->input('receive_updates') == '1' || $request->input('receive_updates') === true) {
            $receiveUpdates = true;
        }

        // Find or create progress record for this game
        $progress = UserGameProgress::firstOrCreate(
            [
                'user_id' => Auth::id(),
                'game_id' => $entry->game_id,
            ],
            [
                'status' => 'custom',
            ]
        );

        $progress->receive_updates = $receiveUpdates;
        $progress->save();

        $message = 'Update notifications ' . ($receiveUpdates ? 'enabled' : 'disabled') . ' for ' . $entry->game->name;

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'receive_updates' => $receiveUpdates,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Toggle update notifications for all entries in a list.
     *
     * @param  Request  $request  The incoming request
     * @param  VnList  $vnList  The list whose entries to update
     */
    public function toggleAllUpdates(Request $request, VnList $vnList): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $vnList);

        // Determine if updates should be received based on the checkbox state
        $receiveUpdates = false;

        // Check if the checkbox is checked (value is '1' or true)
        if ($request->input('receive_updates') == '1' || $request->input('receive_updates') === true) {
            $receiveUpdates = true;
        }

        // Get all game IDs from this list
        $gameIds = $vnList->entries()->pluck('game_id')->toArray();

        // For each game, update or create the user progress record
        foreach ($gameIds as $gameId) {
            UserGameProgress::updateOrCreate(
                [
                    'user_id' => Auth::id(),
                    'game_id' => $gameId,
                ],
                [
                    'receive_updates' => $receiveUpdates,
                ]
            );
        }

        $entriesCount = count($gameIds);
        $message = 'Update notifications ' . ($receiveUpdates ? 'enabled' : 'disabled') . ' for all ' . $entriesCount . ' entries in this list';

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'receive_updates' => $receiveUpdates,
            ]);
        }

        return back()->with('success', $message);
    }
}
