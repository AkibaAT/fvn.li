<div>
    {{-- Header Card --}}
    <div
        class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 md:p-6 border-l-4 border-{{ $vnList->type === 'reading' ? 'blue' : ($vnList->type === 'completed' ? 'green' : ($vnList->type === 'plan_to_read' ? 'yellow' : ($vnList->type === 'on_hold' ? 'orange' : ($vnList->type === 'dropped' ? 'red' : 'gray')))) }}-500 mb-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">{{ $vnList->name }}</h1>
                @if ($vnList->description)
                    <p class="text-gray-600 dark:text-gray-300 mt-2 text-sm">{!! nl2br(e($vnList->description)) !!}</p>
                @endif
                @if (!$isOwner)
                    <div class="mt-2 text-sm text-gray-500">
                        By <a href="{{ route('vn-lists.user-public', $vnList->user) }}"
                              class="text-blue-600 dark:text-blue-400 hover:underline">{{ $vnList->user->name }}</a>
                    </div>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if (!$vnList->is_default)
                    <span
                        class="px-2 py-1 text-xs font-semibold rounded-full bg-{{ $vnList->type === 'reading' ? 'blue' : ($vnList->type === 'completed' ? 'green' : ($vnList->type === 'plan_to_read' ? 'yellow' : ($vnList->type === 'on_hold' ? 'orange' : ($vnList->type === 'dropped' ? 'red' : 'gray')))) }}-100 text-{{ $vnList->type === 'reading' ? 'blue' : ($vnList->type === 'completed' ? 'green' : ($vnList->type === 'plan_to_read' ? 'yellow' : ($vnList->type === 'on_hold' ? 'orange' : ($vnList->type === 'dropped' ? 'red' : 'gray')))) }}-800 dark:bg-{{ $vnList->type === 'reading' ? 'blue' : ($vnList->type === 'completed' ? 'green' : ($vnList->type === 'plan_to_read' ? 'yellow' : ($vnList->type === 'on_hold' ? 'orange' : ($vnList->type === 'dropped' ? 'red' : 'gray')))) }}-900 dark:text-{{ $vnList->type === 'reading' ? 'blue' : ($vnList->type === 'completed' ? 'green' : ($vnList->type === 'plan_to_read' ? 'yellow' : ($vnList->type === 'on_hold' ? 'orange' : ($vnList->type === 'dropped' ? 'red' : 'gray')))) }}-200">
                    {{ ucfirst(str_replace('_', ' ', $vnList->type)) }}
                </span>
                @endif

                @if ($vnList->is_public)
                    <span
                        class="px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                    Public
                </span>
                @endif

                @if ($isOwner)
                    <div class="flex gap-2">
                        <a href="{{ route('vn-lists.index') }}"
                           class="inline-flex items-center px-3 py-1 bg-gray-200 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-800 dark:text-white uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-gray-600 active:bg-gray-400 dark:active:bg-gray-500 focus:outline-none focus:border-gray-400 dark:focus:border-gray-500 focus:ring focus:ring-gray-200 dark:focus:ring-gray-700 transition">
                            Back to Lists
                        </a>

                        <form action="{{ route('vn-lists.toggle-visibility', $vnList) }}" method="POST"
                              class="inline-flex toggle-visibility-form">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center px-3 py-1 bg-{{ $vnList->is_public ? 'purple' : 'gray' }}-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-{{ $vnList->is_public ? 'purple' : 'gray' }}-400 active:bg-{{ $vnList->is_public ? 'purple' : 'gray' }}-600 focus:outline-none focus:border-{{ $vnList->is_public ? 'purple' : 'gray' }}-600 focus:ring focus:ring-{{ $vnList->is_public ? 'purple' : 'gray' }}-200 transition">
                                {{ $vnList->is_public ? 'Make Private' : 'Make Public' }}
                            </button>
                        </form>

                        <a href="{{ route('vn-lists.edit', $vnList) }}"
                           class="inline-flex items-center px-3 py-1 bg-yellow-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-400 active:bg-yellow-600 focus:outline-none focus:border-yellow-600 focus:ring focus:ring-yellow-200 transition">
                            Edit List
                        </a>
                    </div>
                @else
                    <a href="{{ route('vn-lists.public') }}"
                       class="inline-flex items-center px-3 py-1 bg-gray-200 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-800 dark:text-white uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-gray-600 active:bg-gray-400 dark:active:bg-gray-500 focus:outline-none focus:border-gray-400 dark:focus:border-gray-500 focus:ring focus:ring-gray-200 dark:focus:ring-gray-700 transition">
                        Back to Public Lists
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- List Stats Card --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm md:pl-7 md:pr-6 p-4 mb-4">
        <div class="flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                List Entries ({{ $vnList->entries->count() }})
            </h2>
            @if ($isOwner && !$vnList->entries->isEmpty())
                <div class="flex items-center">
                    @php
                        $allGameIds = $vnList->entries->pluck('game_id')->toArray();
                        $userProgressRecords = App\Models\UserGameProgress::where('user_id', auth()->id())
                            ->whereIn('game_id', $allGameIds)
                            ->get()
                            ->keyBy('game_id');

                        $allReceiveUpdates = count($allGameIds) > 0 && count($userProgressRecords) === count($allGameIds) &&
                            $userProgressRecords->every(fn($progress) => $progress->receive_updates);
                    @endphp
                    @include('lists.partials.toggle-switch', [
                        'action' => route('vn-lists.toggle-all-updates', $vnList),
                        'name' => 'receive_updates',
                        'value' => '1',
                        'checked' => $allReceiveUpdates,
                        'srText' => $allReceiveUpdates ? 'Turn off notifications for all entries' : 'Turn on notifications for all entries',
                        'formClass' => 'toggle-all-updates-form',
                        'label' => 'Notifications for all entries:',
                        'extraClass' => 'toggle-all-checkbox',
                        'justify' => 'justify-end'
                    ])
                </div>
            @endif
        </div>
    </div>

    @if ($vnList->entries->isEmpty())
        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-8 text-center">
            <p class="text-gray-500 dark:text-gray-400">No visual novels in this list yet.</p>
            @if ($isOwner)
                <p class="text-gray-500 dark:text-gray-400 mt-2">Browse games and add them to your list!</p>
            @endif
        </div>
    @else
        {{-- Desktop Table Header - Visible only on lg screens and up --}}
        <div
            class="hidden lg:flex bg-gray-100 dark:bg-gray-700 rounded-t-lg p-3 pr-5 font-medium text-sm text-gray-500 dark:text-gray-300 uppercase">
            <div class="w-8"></div> {{-- Drag handle space --}}
            <div class="w-20 mr-2"></div> {{-- Thumbnail space --}}
            <div class="flex-grow">Title</div>
            <div class="w-52">Version</div>
            <div class="w-30">Started</div>
            @if ($vnList->type === 'custom' || $vnList->type === 'completed')
                <div class="w-28">Completed</div>
            @endif
            @if ($isOwner)
                <div class="w-20">Actions</div>
                <div class="w-30 text-right pr-1.5">Notifications</div>
            @endif
        </div>

        {{-- Entry List --}}
        <div id="entries-list"
             class="space-y-3 text-gray-700 dark:text-gray-300 md:grid md:grid-cols-2 md:gap-3 md:space-y-0 lg:block lg:space-y-3">
            @foreach ($vnList->entries->sortBy('sort_order') as $entry)
                <div data-id="{{ $entry->id }}"
                     class="bg-white dark:bg-gray-800 rounded-lg md:rounded-lg lg:rounded-none shadow-sm">
                    {{-- Desktop View --}}
                    <div class="hidden lg:flex items-center p-3 pr-5{{ $isOwner ? ' cursor-move' : '' }}">
                        @if ($isOwner)
                            {{-- Drag Handle --}}
                            <div class="w-8 flex">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor"
                                     viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M4 8h16M4 16h16"></path>
                                </svg>
                            </div>
                        @else
                            <div class="w-8"></div>
                        @endif

                        {{-- Thumbnail --}}
                        <div class="w-20 mr-2">
                            <a href="{{ route('games.show', $entry->game->slug) }}">
                                <x-game-thumbnail :game="$entry->game" variant="small"
                                                  class="w-18 h-18 object-cover rounded"/>
                            </a>
                        </div>

                        {{-- Title --}}
                        <div class="flex-grow">
                            <a href="{{ route('games.show', $entry->game->slug) }}"
                               class="font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                {{ $entry->game->name }}
                            </a>
                            @php
                                $userProgress = null;
                                $currentVersion = null;
                                $ownerProgress = null;

                                if ($isOwner || auth()->check()) {
                                    // Get the viewing user's progress (for logged-in users viewing lists)
                                    $userProgress = $entry->game->userProgress()
                                        ->where('user_id', auth()->id())
                                        ->with('gameVersion')
                                        ->first();
                                    $currentVersion = $userProgress?->gameVersion;
                                }

                                // For public lists, we also need the list owner's progress
                                if (!$isOwner && !$userProgress) {
                                    $ownerProgress = $entry->game->userProgress()
                                        ->where('user_id', $vnList->user_id)
                                        ->with('gameVersion')
                                        ->first();
                                    $currentVersion = $ownerProgress?->gameVersion;
                                }
                            @endphp

                            @if ($userProgress?->personal_notes)
                                <div class="text-xs italic truncate max-w-md">
                                    "{{ $userProgress->personal_notes }}"
                                </div>
                            @elseif ($ownerProgress?->personal_notes)
                                <div class="text-xs italic truncate max-w-md">
                                    "{{ $ownerProgress->personal_notes }}"
                                </div>
                            @endif
                        </div>

                        {{-- Version --}}
                        <div class="w-52">
                            @if ($currentVersion)
                                <div @class([
                                    'border-l-4 pl-3',
                                    'border-yellow-500' => $entry->game->latestVersion && $currentVersion->id !== $entry->game->latestVersion->id,
                                    'border-transparent' => !$entry->game->latestVersion || $currentVersion->id === $entry->game->latestVersion->id,
                                ])>
                                    v{{ $currentVersion->version }}
                                    <span class="text-gray-400">
                                        ({{ $currentVersion->published_at->format('Y-m-d') }})
                                    </span>

                                    @include('games.components.version-info-block', [
                                        'currentVersion' => $currentVersion,
                                        'game' => $entry->game,
                                        'layout' => 'desktop'
                                    ])
                                </div>
                            @else
                                <span class="text-gray-500 dark:text-gray-400 text-xs">Not started</span>
                            @endif
                        </div>

                        {{-- Started Date --}}
                        <div class="w-30 text-sm">
                            @if ($isOwner || (auth()->check() && $userProgress))
                                {{ $userProgress?->started_at ? $userProgress->started_at->format('M d, Y') : '-' }}
                            @elseif ($ownerProgress)
                                {{ $ownerProgress->started_at ? $ownerProgress->started_at->format('M d, Y') : '-' }}
                            @else
                                -
                            @endif
                        </div>

                        {{-- Completed Date (if applicable) --}}
                        @if ($vnList->type === 'custom' || $vnList->type === 'completed')
                            <div class="w-28 text-sm">
                                @if ($isOwner || (auth()->check() && $userProgress))
                                    {{ $userProgress?->completed_at ? $userProgress->completed_at->format('M d, Y') : '-' }}
                                @elseif ($ownerProgress)
                                    {{ $ownerProgress->completed_at ? $ownerProgress->completed_at->format('M d, Y') : '-' }}
                                @else
                                    -
                                @endif
                            </div>
                        @endif

                        {{-- Actions --}}
                        @if ($isOwner)
                            <div class="w-20 text-sm space-y-2">
                                <button
                                    onclick="toggleEditForm('{{ $entry->id }}')"
                                    class="block w-full text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 cursor-pointer text-left">
                                    Edit
                                </button>

                                @php
                                    $listsWithThisGame = auth()->user()->vnLists()
                                        ->whereHas('entries', function($query) use ($entry) {
                                            $query->where('game_id', $entry->game_id);
                                        })
                                        ->pluck('id')
                                        ->toArray();
                                    $canMoveToAnyList = count(auth()->user()->vnLists) - 1 > count($listsWithThisGame);
                                @endphp

                                @if ($canMoveToAnyList)
                                    <button
                                        onclick="toggleMoveForm('{{ $entry->id }}')"
                                        class="block w-full text-yellow-600 dark:text-yellow-400 hover:text-yellow-800 dark:hover:text-yellow-300 cursor-pointer text-left">
                                        Move
                                    </button>
                                @endif

                                <form action="{{ route('list-entries.destroy', $entry) }}" method="POST"
                                      class="block remove-entry-form">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="w-full text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 cursor-pointer text-left">
                                        Remove
                                    </button>
                                </form>
                            </div>
                            <div class="w-30 pr-1">
                                <x-games::notification-toggle :game="$entry->game" />
                            </div>
                        @endif
                    </div>

                    {{-- Mobile/Tablet View --}}
                    <div class="flex lg:hidden p-4 relative{{ $isOwner ? ' cursor-move' : '' }}">
                        @if ($isOwner)
                            <div class="absolute -left-1 top-1/2 transform -translate-y-1/2 flex items-center">
                                <div
                                    class="w-6 h-10 flex items-center justify-center bg-gray-100 dark:bg-gray-700 rounded-r-md">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor"
                                         viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M4 8h16M4 16h16"></path>
                                    </svg>
                                </div>
                            </div>
                        @endif

                        <div class="flex gap-4 @if ($isOwner) pl-4 @endif">
                            {{-- Thumbnail --}}
                            <a href="{{ route('games.show', $entry->game->slug) }}">
                                <x-game-thumbnail :game="$entry->game" variant="small"
                                                  class="w-32 h-32 object-cover rounded"/>
                            </a>

                            {{-- Game Info --}}
                            <div class="flex-1">
                                <a href="{{ route('games.show', $entry->game->slug) }}"
                                   class="text-lg font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                    {{ $entry->game->name }}
                                </a>

                                <div class="flex items-center gap-2 mt-2">
                                    {{-- Version Badge --}}
                                    @if ($currentVersion)
                                        <span class="text-xs px-2 py-1 rounded-full mb-1
                                            @if ($entry->game->latestVersion && $currentVersion->id !== $entry->game->latestVersion->id)
                                                bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300
                                            @else
                                                bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                                            @endif
                                        ">
                                            v{{ $currentVersion->version }}
                                        </span>
                                    @endif
                                </div>

                                <div class="text-sm">
                                    {{-- Started Date --}}
                                    <div>
                                        <span>Started:</span>
                                        <span class="ml-1">
                                            @if ($isOwner || (auth()->check() && $userProgress))
                                                {{ $userProgress?->started_at ? $userProgress->started_at->format('M d, Y') : 'Not started' }}
                                            @elseif ($ownerProgress)
                                                {{ $ownerProgress->started_at ? $ownerProgress->started_at->format('M d, Y') : 'Not started' }}
                                            @else
                                                Not started
                                            @endif
                                        </span>
                                    </div>

                                    {{-- Completed Date --}}
                                    @if ($vnList->type === 'custom' || $vnList->type === 'completed')
                                        <div>
                                            <span>Completed:</span>
                                            <span class="ml-1">
                                                @if ($isOwner || (auth()->check() && $userProgress))
                                                    {{ $userProgress?->completed_at ? $userProgress->completed_at->format('M d, Y') : '-' }}
                                                @elseif ($ownerProgress)
                                                    {{ $ownerProgress->completed_at ? $ownerProgress->completed_at->format('M d, Y') : '-' }}
                                                @else
                                                    -
                                                @endif
                                            </span>
                                        </div>
                                    @endif

                                    {{-- Update Available Notice --}}
                                    @if ($currentVersion && $entry->game->latestVersion && $currentVersion->id !== $entry->game->latestVersion->id)
                                        @include('games.components.version-info-block', [
                                            'currentVersion' => $currentVersion,
                                            'game' => $entry->game,
                                            'layout' => 'mobile'
                                        ])
                                    @endif

                                    {{-- Notes Preview (truncated) --}}
                                    @if ($userProgress?->personal_notes)
                                        <div class="mt-1 text-xs italic line-clamp-1">
                                            "{{ $userProgress->personal_notes }}"
                                        </div>
                                    @elseif ($ownerProgress?->personal_notes)
                                        <div class="mt-1 text-xs italic line-clamp-1">
                                            "{{ $ownerProgress->personal_notes }}"
                                        </div>
                                    @endif
                                </div>

                                {{-- Action Buttons (Mobile) --}}
                                @if ($isOwner)
                                    <div class="flex flex-col gap-3 mt-3">
                                        {{-- Action Buttons --}}
                                        <div class="flex text-sm space-x-2">
                                            <button
                                                onclick="toggleEditForm('{{ $entry->id }}')"
                                                class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 cursor-pointer">
                                                Edit
                                            </button>

                                            @if ($canMoveToAnyList)
                                                <button
                                                    onclick="toggleMoveForm('{{ $entry->id }}')"
                                                    class="text-yellow-600 dark:text-yellow-400 hover:text-yellow-800 dark:hover:text-yellow-300 cursor-pointer">
                                                    Move
                                                </button>
                                            @endif

                                            <form action="{{ route('list-entries.destroy', $entry) }}" method="POST"
                                                  class="inline remove-entry-form">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 cursor-pointer">
                                                    Remove
                                                </button>
                                            </form>
                                        </div>

                                        <div class="h-px bg-gray-200 dark:bg-gray-700"></div>

                                        {{-- Notification Toggle --}}
                                        <x-games::notification-toggle :game="$entry->game" :justify="'justify-start'" :label="'Notifications'" />
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Edit Form - Same for both mobile and desktop --}}
                    @if ($isOwner)
                        <div id="edit-form-{{ $entry->id }}"
                             class="hidden border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700 p-4">
                            <form action="{{ route('user-progress.update', $entry->game->id) }}" method="POST"
                                  class="space-y-4 entry-edit-form">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="game_id" value="{{ $entry->game->id }}">
                                <div
                                    class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-{{ ($vnList->type === 'custom' || $vnList->type === 'completed') ? '3' : '2' }} gap-4">
                                    <div>
                                        <label for="game_version_id-{{ $entry->id }}"
                                               class="block text-sm font-medium text-gray-700 dark:text-gray-300">Last
                                            Read Version</label>
                                        <select id="game_version_id-{{ $entry->id }}" name="game_version_id"
                                                class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md py-2 px-3">
                                            <option value="">Not started</option>
                                            @foreach ($entry->game->gameVersions()->orderByDesc('published_at')->get() as $version)
                                                <option
                                                    value="{{ $version->id }}" {{ $userProgress?->game_version_id == $version->id ? 'selected' : '' }}>
                                                    {{ $version->version }}
                                                    ({{ $version->published_at->format('Y-m-d') }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <label for="started_at-{{ $entry->id }}"
                                               class="block text-sm font-medium text-gray-700 dark:text-gray-300">Started
                                            At</label>
                                        <div class="relative">
                                            <input type="date" id="started_at-{{ $entry->id }}" name="started_at"
                                                   value="{{ $userProgress?->started_at ? $userProgress->started_at->format('Y-m-d') : '' }}"
                                                   class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md py-2 px-3 date-input">
                                            <div
                                                class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 mt-1">
                                                <svg class="h-5 w-5 text-gray-500 dark:text-gray-400"
                                                     xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                                     fill="currentColor">
                                                    <path fill-rule="evenodd"
                                                          d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z"
                                                          clip-rule="evenodd"/>
                                                </svg>
                                            </div>
                                        </div>
                                    </div>

                                    @if ($vnList->type === 'custom' || $vnList->type === 'completed')
                                        <div>
                                            <label for="completed_at-{{ $entry->id }}"
                                                   class="block text-sm font-medium text-gray-700 dark:text-gray-300">Completed
                                                At</label>
                                            <div class="relative">
                                                <input type="date" id="completed_at-{{ $entry->id }}"
                                                       name="completed_at"
                                                       value="{{ $userProgress?->completed_at ? $userProgress->completed_at->format('Y-m-d') : '' }}"
                                                       class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md py-2 px-3 date-input">
                                                <div
                                                    class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 mt-1">
                                                    <svg class="h-5 w-5 text-gray-500 dark:text-gray-400"
                                                         xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                                         fill="currentColor">
                                                        <path fill-rule="evenodd"
                                                              d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z"
                                                              clip-rule="evenodd"/>
                                                    </svg>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <div>
                                    <label for="personal_notes-{{ $entry->id }}"
                                           class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
                                    <textarea id="personal_notes-{{ $entry->id }}" name="personal_notes" rows="4"
                                              class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md py-2 px-3">{{ $userProgress?->personal_notes }}</textarea>
                                </div>

                                <div class="flex justify-end space-x-2">
                                    <button type="button" onclick="toggleEditForm('{{ $entry->id }}')"
                                            class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Cancel
                                    </button>
                                    <button type="submit"
                                            class="inline-flex items-center px-3 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>

                        {{-- Move Form --}}
                        <div id="move-form-{{ $entry->id }}"
                             class="hidden border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700 p-4">
                            <form action="{{ route('list-entries.move', $entry) }}" method="POST"
                                  class="move-entry-form">
                                @csrf

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="target_list_id-{{ $entry->id }}"
                                               class="block text-sm font-medium text-gray-700 dark:text-gray-300">Target
                                            List</label>
                                        @php
                                            $listsWithThisGame = auth()->user()->vnLists()
                                                ->whereHas('entries', function($query) use ($entry) {
                                                    $query->where('game_id', $entry->game_id);
                                                })
                                                ->pluck('id')
                                                ->toArray();

                                            // Get available lists for this user
                                            $availableLists = auth()->user()->vnLists
                                                ->filter(function($targetList) use ($vnList, $listsWithThisGame) {
                                                    return $targetList->id !== $vnList->id && !in_array($targetList->id, $listsWithThisGame);
                                                });

                                            // Sort lists using the same logic as in SortsVnLists trait
                                            $typeOrder = [
                                                'plan_to_read' => 1,
                                                'reading' => 2,
                                                'completed' => 3,
                                                'on_hold' => 4,
                                                'dropped' => 5,
                                            ];

                                            $availableLists = $availableLists->sort(function ($a, $b) use ($typeOrder) {
                                                // If both are standard types, use the predefined order
                                                if (isset($typeOrder[$a->type]) && isset($typeOrder[$b->type])) {
                                                    return $typeOrder[$a->type] <=> $typeOrder[$b->type];
                                                }

                                                // If only one is a standard type, it comes first
                                                if (isset($typeOrder[$a->type])) {
                                                    return -1;
                                                }
                                                if (isset($typeOrder[$b->type])) {
                                                    return 1;
                                                }

                                                // Both are custom lists, sort alphabetically by name
                                                return $a->name <=> $b->name;
                                            });
                                        @endphp

                                        <select id="target_list_id-{{ $entry->id }}" name="target_list_id"
                                                class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md py-2 px-3">
                                            @foreach ($availableLists as $targetList)
                                                <option value="{{ $targetList->id }}">
                                                    {{ $targetList->name }}
                                                    ({{ ucfirst(str_replace('_', ' ', $targetList->type)) }})
                                                </option>
                                            @endforeach
                                        </select>
                                        @if (count(auth()->user()->vnLists) - 1 === count($listsWithThisGame))
                                            <p class="mt-2 text-sm text-red-600 dark:text-red-400">
                                                This VN is already in all of your other lists.
                                            </p>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex justify-end space-x-2 mt-4">
                                    <button type="button" onclick="toggleMoveForm('{{ $entry->id }}')"
                                            class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Cancel
                                    </button>
                                    <button type="submit"
                                            class="inline-flex items-center px-3 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                                        Move to List
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <livewire:components.version-comparison />

    <style>
        /* Hide the default calendar icon but keep the functionality */
        input[type="date"]::-webkit-calendar-picker-indicator {
            opacity: 0;
            width: 100%;
            height: 100%;
            position: absolute;
            top: 0;
            left: 0;
            cursor: pointer;
        }

        /* Additional padding for the date inputs to accommodate the custom icon */
        .date-input {
            padding-right: 2.5rem;
        }

        /* Custom class to display line-clamping (truncated text with ellipsis) */
        .line-clamp-1 {
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Ghost class for Sortable.js */
        .sortable-ghost {
            opacity: 0.6;
            background-color: rgba(209, 213, 219, 0.5);
        }

        .dark .sortable-ghost {
            background-color: rgba(55, 65, 81, 0.5);
        }
    </style>

    <script>
        // Version comparison functionality
        function compareGameVersions(fromVersionId, toVersionId, gameId) {
            Livewire.dispatch('compare-game-versions', {
                params: {
                    fromVersionId: fromVersionId,
                    toVersionId: toVersionId,
                    gameId: gameId
                }
            });
        }

        document.addEventListener('open-dialog', (e) => {
            document.getElementById(e.detail.dialogId).showModal();
        });

        // Close dialog when clicking outside
        document.querySelectorAll('dialog').forEach(dialog => {
            dialog.addEventListener('click', (e) => {
                if (e.target === e.currentTarget) {
                    e.currentTarget.close();
                }
            });
        });

        function toggleEditForm(entryId) {
            const editForm = document.getElementById('edit-form-' + entryId);
            editForm.classList.toggle('hidden');

            // Hide move form if it's open
            const moveForm = document.getElementById('move-form-' + entryId);
            if (moveForm && !moveForm.classList.contains('hidden')) {
                moveForm.classList.add('hidden');
            }
        }

        function toggleMoveForm(entryId) {
            const moveForm = document.getElementById('move-form-' + entryId);
            moveForm.classList.toggle('hidden');

            // Hide edit form if it's open
            const editForm = document.getElementById('edit-form-' + entryId);
            if (editForm && !editForm.classList.contains('hidden')) {
                editForm.classList.add('hidden');
            }
        }
    </script>

    @if ($isOwner)
        <script>
            // Initialize Sortable.js for drag and drop reordering
            document.addEventListener('DOMContentLoaded', function () {
                const entriesList = document.getElementById('entries-list');
                if (entriesList) {
                    new Sortable(entriesList, {
                        animation: 150,
                        handle: '.cursor-move',
                        ghostClass: 'sortable-ghost',
                        onEnd: async function (evt) {
                            const ids = Array.from(entriesList.children)
                                .filter(el => el.hasAttribute('data-id'))
                                .map(el => el.dataset.id);

                            try {
                                const response = await fetch('{{ route('vn-lists.update-order', $vnList) }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                    },
                                    body: JSON.stringify({entries: ids})
                                });

                                const data = await response.json();

                                if (data.success) {
                                    showSuccessMessage(data.message);
                                } else {
                                    console.error('Failed to update order:', data.message);
                                    showErrorMessage('Failed to update order: ' + data.message);
                                }
                            } catch (error) {
                                console.error('Error updating order:', error);
                                showErrorMessage('Error updating order. Please try again.');
                            }
                        }
                    });
                }

                // Set up event handlers for forms
                document.querySelectorAll('.entry-edit-form').forEach(form => {
                    form.addEventListener('submit', async function (e) {
                        e.preventDefault();

                        try {
                            const formData = new FormData(this);

                            // Handle empty select values
                            const versionSelect = this.querySelector('[name="game_version_id"]');
                            if (versionSelect && versionSelect.value === '') {
                                formData.set('game_version_id', ''); // Ensure it's an empty string not null
                            }

                            const response = await fetch(this.action, {
                                method: 'POST',
                                body: formData,
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            });

                            const data = await response.json();

                            if (data.success) {
                                window.location.reload();
                            } else {
                                console.error('Error updating entry:', data.message);
                                alert('Failed to update entry: ' + data.message);
                            }
                        } catch (error) {
                            console.error('Error:', error);
                            alert('An error occurred. Please try again.');
                        }
                    });
                });

                // Helper function to show success messages
                function showSuccessMessage(message) {
                    const successMessage = document.createElement('div');
                    successMessage.className = 'fixed bottom-4 right-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded shadow-lg z-50';
                    successMessage.innerHTML = `<p>${message}</p>`;
                    document.body.appendChild(successMessage);

                    // Remove the message after 3 seconds
                    setTimeout(() => {
                        successMessage.remove();
                    }, 3000);
                }

                // Helper function to show error messages
                function showErrorMessage(message) {
                    const errorMessage = document.createElement('div');
                    errorMessage.className = 'fixed bottom-4 right-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded shadow-lg z-50';
                    errorMessage.innerHTML = `<p>${message}</p>`;
                    document.body.appendChild(errorMessage);

                    // Remove the message after 5 seconds
                    setTimeout(() => {
                        errorMessage.remove();
                    }, 5000);
                }

                // Set up event handlers for toggle visibility form
                document.querySelectorAll('.toggle-visibility-form').forEach(form => {
                    form.addEventListener('submit', async function (e) {
                        e.preventDefault();

                        try {
                            const formData = new FormData(this);
                            const response = await fetch(this.action, {
                                method: 'POST',
                                body: formData,
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            });

                            const data = await response.json();

                            if (data.success) {
                                // Update the button text
                                const button = this.querySelector('button');
                                if (button) {
                                    button.textContent = button.textContent === 'Make Private' ? 'Make Public' : 'Make Private';

                                    // Update button classes
                                    button.classList.toggle('bg-purple-500');
                                    button.classList.toggle('bg-gray-500');
                                    button.classList.toggle('hover:bg-purple-400');
                                    button.classList.toggle('hover:bg-gray-400');
                                    button.classList.toggle('active:bg-purple-600');
                                    button.classList.toggle('active:bg-gray-600');
                                    button.classList.toggle('focus:border-purple-600');
                                    button.classList.toggle('focus:border-gray-600');
                                    button.classList.toggle('focus:ring-purple-200');
                                    button.classList.toggle('focus:ring-gray-200');
                                }

                                // Toggle the public badge
                                const header = document.querySelector('.flex.justify-between.items-center.mb-6');
                                if (header) {
                                    const publicBadge = header.querySelector('.bg-purple-100');
                                    if (publicBadge) {
                                        // If badge exists, remove it
                                        publicBadge.remove();
                                    } else {
                                        // If badge doesn't exist, add it
                                        const badge = document.createElement('span');
                                        badge.className = 'px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200';
                                        badge.textContent = 'Public';

                                        // Insert the badge in the right container
                                        const badgeContainer = header.querySelector('.flex.items-center.space-x-2');
                                        if (badgeContainer) {
                                            const firstButton = badgeContainer.querySelector('a, form');
                                            badgeContainer.insertBefore(badge, firstButton);
                                        }
                                    }
                                }

                                // Show success message
                                showSuccessMessage(data.message);
                            } else {
                                console.error('Error toggling visibility:', data.message);
                                showErrorMessage('Failed to toggle visibility: ' + data.message);
                            }
                        } catch (error) {
                            console.error('Error:', error);
                            showErrorMessage('An error occurred. Please try again.');
                        }
                    });
                });

                document.querySelectorAll('.move-entry-form').forEach(form => {
                    form.addEventListener('submit', async function (e) {
                        e.preventDefault();

                        try {
                            const formData = new FormData(this);
                            const response = await fetch(this.action, {
                                method: 'POST',
                                body: formData,
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            });

                            const data = await response.json();

                            if (data.success) {
                                window.location.reload();
                            } else {
                                console.error('Error moving entry:', data.message);
                                alert('Failed to move entry: ' + data.message);
                            }
                        } catch (error) {
                            console.error('Error:', error);
                            alert('An error occurred. Please try again.');
                        }
                    });
                });

                document.querySelectorAll('.remove-entry-form').forEach(form => {
                    form.addEventListener('submit', async function (e) {
                        e.preventDefault();

                        if (!confirm('Are you sure you want to remove this entry?')) {
                            return;
                        }

                        try {
                            const formData = new FormData(this);
                            const response = await fetch(this.action, {
                                method: 'POST',
                                body: formData,
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                }
                            });

                            const data = await response.json();

                            if (data.success) {
                                window.location.reload();
                            } else {
                                console.error('Error removing entry:', data.message);
                                alert('Failed to remove entry: ' + data.message);
                            }
                        } catch (error) {
                            console.error('Error:', error);
                            alert('An error occurred. Please try again.');
                        }
                    });
                });




            });
        </script>
    @endif
</div>
