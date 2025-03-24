<div>
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 border-l-4 border-{{ $vnList->type === 'reading' ? 'blue' : ($vnList->type === 'completed' ? 'green' : ($vnList->type === 'plan_to_read' ? 'yellow' : ($vnList->type === 'on_hold' ? 'orange' : ($vnList->type === 'dropped' ? 'red' : 'gray')))) }}-500">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $vnList->name }}</h1>
                <div class="mt-1 flex items-center space-x-2">
                    {{-- Public tag moved to top-right --}}
                </div>

                @if (!$isOwner)
                    <div class="mt-2 text-sm text-gray-500">
                        By <a href="{{ route('vn-lists.user-public', $vnList->user) }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $vnList->user->name }}</a>
                    </div>
                @endif
            </div>
            <div class="flex items-center space-x-2">
                @if (!$vnList->is_default)
                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-{{ $vnList->type === 'reading' ? 'blue' : ($vnList->type === 'completed' ? 'green' : ($vnList->type === 'plan_to_read' ? 'yellow' : ($vnList->type === 'on_hold' ? 'orange' : ($vnList->type === 'dropped' ? 'red' : 'gray')))) }}-100 text-{{ $vnList->type === 'reading' ? 'blue' : ($vnList->type === 'completed' ? 'green' : ($vnList->type === 'plan_to_read' ? 'yellow' : ($vnList->type === 'on_hold' ? 'orange' : ($vnList->type === 'dropped' ? 'red' : 'gray')))) }}-800 dark:bg-{{ $vnList->type === 'reading' ? 'blue' : ($vnList->type === 'completed' ? 'green' : ($vnList->type === 'plan_to_read' ? 'yellow' : ($vnList->type === 'on_hold' ? 'orange' : ($vnList->type === 'dropped' ? 'red' : 'gray')))) }}-900 dark:text-{{ $vnList->type === 'reading' ? 'blue' : ($vnList->type === 'completed' ? 'green' : ($vnList->type === 'plan_to_read' ? 'yellow' : ($vnList->type === 'on_hold' ? 'orange' : ($vnList->type === 'dropped' ? 'red' : 'gray')))) }}-200">
                    {{ ucfirst(str_replace('_', ' ', $vnList->type)) }}
                </span>
                @endif

                @if ($vnList->is_public)
                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                    Public
                </span>
                @endif

                @if ($isOwner)
                    <a href="{{ route('vn-lists.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-800 dark:text-white uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-gray-600 active:bg-gray-400 dark:active:bg-gray-500 focus:outline-none focus:border-gray-400 dark:focus:border-gray-500 focus:ring focus:ring-gray-200 dark:focus:ring-gray-700 transition">
                        Back to Lists
                    </a>

                    <form action="{{ route('vn-lists.toggle-visibility', $vnList) }}" method="POST" class="inline-flex toggle-visibility-form">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-{{ $vnList->is_public ? 'purple' : 'gray' }}-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-{{ $vnList->is_public ? 'purple' : 'gray' }}-400 active:bg-{{ $vnList->is_public ? 'purple' : 'gray' }}-600 focus:outline-none focus:border-{{ $vnList->is_public ? 'purple' : 'gray' }}-600 focus:ring focus:ring-{{ $vnList->is_public ? 'purple' : 'gray' }}-200 transition">
                            {{ $vnList->is_public ? 'Make Private' : 'Make Public' }}
                        </button>
                    </form>

                    <a href="{{ route('vn-lists.edit', $vnList) }}" class="inline-flex items-center px-4 py-2 bg-yellow-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-400 active:bg-yellow-600 focus:outline-none focus:border-yellow-600 focus:ring focus:ring-yellow-200 transition">
                        Edit List
                    </a>
                @else
                    <a href="{{ route('vn-lists.public') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-800 dark:text-white uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-gray-600 active:bg-gray-400 dark:active:bg-gray-500 focus:outline-none focus:border-gray-400 dark:focus:border-gray-500 focus:ring focus:ring-gray-200 dark:focus:ring-gray-700 transition">
                        Back to Public Lists
                    </a>
                @endif
            </div>
        </div>

        @if (session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p>{{ session('success') }}</p>
            </div>
        @endif

        @if (session('error'))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p>{{ session('error') }}</p>
            </div>
        @endif

        @if ($vnList->description)
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-2">Description</h2>
                <p class="text-gray-600 dark:text-gray-300">{!! nl2br(e($vnList->description)) !!}</p>
            </div>
        @endif

        <div class="mb-6">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">List Entries ({{ $vnList->entries->count() }})</h2>

            @if ($vnList->entries->isEmpty())
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 text-center">
                    <p class="text-gray-500 dark:text-gray-400">No visual novels in this list yet.</p>
                    @if ($isOwner)
                        <p class="text-gray-500 dark:text-gray-400 mt-2">Browse games and add them to your list!</p>
                    @endif
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white dark:bg-gray-800 rounded-lg overflow-hidden">
                        <thead class="bg-gray-100 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Visual Novel</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Last Read Version</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Started</th>
                                @if ($vnList->type === 'custom' || $vnList->type === 'completed')
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Completed</th>
                                @endif
                                @if ($isOwner)
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700" id="entries-list">
                            @foreach ($vnList->entries->sortBy('sort_order') as $entry)
                                <tr data-id="{{ $entry->id }}" @if ($isOwner) class="cursor-move" @endif>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            @if ($isOwner)
                                                <div class="flex-shrink-0 mr-3 text-gray-400 dark:text-gray-600">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                                                    </svg>
                                                </div>
                                            @endif
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <img class="h-10 w-10 rounded-md object-cover" src="{{ $entry->game->getThumbnailUrl() }}" alt="{{ $entry->game->name }}">
                                            </div>
                                            <div class="ml-4">
                                                <a href="{{ route('games.show', $entry->game->slug) }}" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                                    {{ $entry->game->name }}
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
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
                                            }
                                        @endphp

                                        @if ($currentVersion)
                                            <div @class([
                                                'border-l-4 pl-3',
                                                'border-yellow-500' => $entry->game->latestVersion && $currentVersion->id !== $entry->game->latestVersion->id,
                                                'border-transparent' => !$entry->game->latestVersion || $currentVersion->id === $entry->game->latestVersion->id,
                                            ])>
                                                {{ $currentVersion->version }}
                                                <span class="text-gray-400">({{ $currentVersion->published_at->format('Y-m-d') }})</span>

                                                @if ($entry->game->latestVersion && $currentVersion->id !== $entry->game->latestVersion->id)
                                                    <div class="text-yellow-600 dark:text-yellow-400 text-xs mt-1">
                                                        Latest: {{ $entry->game->latestVersion->version }}
                                                        ({{ $entry->game->latestVersion->published_at->format('Y-m-d') }})
                                                    </div>
                                                @endif

                                                @php
                                                    $currentVersionStats = $currentVersion->getStatsForLanguage('eng');
                                                    $latestVersionStats = $entry->game->latestVersion?->getStatsForLanguage('eng');
                                                    $wordDiff = null;
                                                    if ($currentVersionStats && $latestVersionStats && $currentVersionStats->words !== $latestVersionStats->words) {
                                                        $wordDiff = $latestVersionStats->words - $currentVersionStats->words;
                                                    }
                                                @endphp

                                                @if ($wordDiff !== null)
                                                    <div class="text-xs mt-1">
                                                        <span class="text-gray-500">Words:</span>
                                                        {{ number_format($currentVersionStats->words) }}
                                                        <span @class([
                                                            'ml-1',
                                                            'text-green-600 dark:text-green-400' => $wordDiff > 0,
                                                            'text-red-600 dark:text-red-400' => $wordDiff < 0,
                                                        ])>
                                                            ({{ $wordDiff > 0 ? '+' : '' }}{{ number_format($wordDiff) }})
                                                        </span>
                                                    </div>
                                                @endif

                                                @if ($entry->game->latestVersion && $currentVersion->id !== $entry->game->latestVersion->id)
                                                    @php
                                                        $hasCurrentStats = $currentVersion->characterStats()->exists();
                                                        $hasLatestStats = $entry->game->latestVersion->characterStats()->exists();
                                                    @endphp

                                                    @if ($hasCurrentStats && $hasLatestStats)
                                                    <button type="button"
                                                            class="text-xs text-blue-600 dark:text-blue-400 hover:underline mt-1 inline-flex items-center"
                                                            onclick="compareGameVersions('{{ $currentVersion->id }}', '{{ $entry->game->latestVersion->id }}', '{{ $entry->game->id }}')">
                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                        </svg>
                                                        Compare changes
                                                    </button>
                                                    @endif
                                                @endif
                                            </div>
                                        @elseif ($ownerProgress && $ownerProgress->gameVersion)
                                            {{-- Show the list owner's version when viewing a public list --}}
                                            <div @class([
                                                'border-l-4 pl-3',
                                                'border-yellow-500' => $entry->game->latestVersion && $ownerProgress->gameVersion->id !== $entry->game->latestVersion->id,
                                                'border-transparent' => !$entry->game->latestVersion || $ownerProgress->gameVersion->id === $entry->game->latestVersion->id,
                                            ])>
                                                {{ $ownerProgress->gameVersion->version }}
                                                <span class="text-gray-400">({{ $ownerProgress->gameVersion->published_at->format('Y-m-d') }})</span>

                                                @if ($entry->game->latestVersion && $ownerProgress->gameVersion->id !== $entry->game->latestVersion->id)
                                                    <div class="text-yellow-600 dark:text-yellow-400 text-xs mt-1">
                                                        Latest: {{ $entry->game->latestVersion->version }}
                                                        ({{ $entry->game->latestVersion->published_at->format('Y-m-d') }})
                                                    </div>
                                                @endif

                                                @php
                                                    $ownerVersionStats = $ownerProgress->gameVersion->getStatsForLanguage('eng');
                                                    $latestVersionStats = $entry->game->latestVersion?->getStatsForLanguage('eng');
                                                    $wordDiff = null;
                                                    if ($ownerVersionStats && $latestVersionStats && $ownerVersionStats->words !== $latestVersionStats->words) {
                                                        $wordDiff = $latestVersionStats->words - $ownerVersionStats->words;
                                                    }
                                                @endphp

                                                @if ($wordDiff !== null)
                                                    <div class="text-xs mt-1">
                                                        <span class="text-gray-500">Words:</span>
                                                        {{ number_format($ownerVersionStats->words) }}
                                                        <span @class([
                                                            'ml-1',
                                                            'text-green-600 dark:text-green-400' => $wordDiff > 0,
                                                            'text-red-600 dark:text-red-400' => $wordDiff < 0,
                                                        ])>
                                                            ({{ $wordDiff > 0 ? '+' : '' }}{{ number_format($wordDiff) }})
                                                        </span>
                                                    </div>
                                                @endif

                                                @if ($ownerProgress->gameVersion->id !== $entry->game->latestVersion->id)
                                                    @php
                                                        $hasOwnerStats = $ownerProgress->gameVersion->characterStats()->exists();
                                                        $hasLatestStats = $entry->game->latestVersion->characterStats()->exists();
                                                    @endphp

                                                    @if ($hasOwnerStats && $hasLatestStats)
                                                    <button type="button"
                                                            class="text-xs text-blue-600 dark:text-blue-400 hover:underline mt-1 inline-flex items-center"
                                                            onclick="compareGameVersions('{{ $ownerProgress->gameVersion->id }}', '{{ $entry->game->latestVersion->id }}', '{{ $entry->game->id }}')">
                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                        </svg>
                                                        Compare changes
                                                    </button>
                                                    @endif
                                                @endif
                                            </div>
                                        @elseif ($entry->game->latestVersion)
                                            {{-- Show latest version for public lists when no user progress is available --}}
                                            <div class="pl-3">
                                                {{ $entry->game->latestVersion->version }}
                                                <span class="text-gray-400">({{ $entry->game->latestVersion->published_at->format('Y-m-d') }})</span>

                                                @php
                                                    $latestVersionStats = $entry->game->latestVersion->getStatsForLanguage('eng');
                                                @endphp

                                                @if ($latestVersionStats)
                                                    <div class="text-xs mt-1">
                                                        <span class="text-gray-500">Words:</span>
                                                        {{ number_format($latestVersionStats->words) }}
                                                    </div>
                                                @endif
                                            </div>
                                        @else
                                            Not available
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                        @if ($isOwner || (auth()->check() && $userProgress))
                                            {{ $userProgress?->started_at ? $userProgress->started_at->format('M d, Y') : '-' }}
                                        @elseif ($ownerProgress)
                                            {{ $ownerProgress->started_at ? $ownerProgress->started_at->format('M d, Y') : '-' }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    @if ($vnList->type === 'custom' || $vnList->type === 'completed')
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                            @if ($isOwner || (auth()->check() && $userProgress))
                                                {{ $userProgress?->completed_at ? $userProgress->completed_at->format('M d, Y') : '-' }}
                                            @elseif ($ownerProgress)
                                                {{ $ownerProgress->completed_at ? $ownerProgress->completed_at->format('M d, Y') : '-' }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                    @endif
                                    @if ($isOwner)
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                            <button type="button"
                                                    class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 cursor-pointer"
                                                    onclick="toggleEditForm('{{ $entry->id }}')">
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
                                            <button type="button"
                                                    class="text-yellow-600 dark:text-yellow-400 hover:text-yellow-800 dark:hover:text-yellow-300 cursor-pointer"
                                                    onclick="toggleMoveForm('{{ $entry->id }}')">
                                                Move
                                            </button>
                                            @endif

                                            <form action="{{ route('list-entries.destroy', $entry) }}" method="POST" class="inline remove-entry-form">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 cursor-pointer">
                                                    Remove
                                                </button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>

                                @if ($isOwner)
                                    <!-- Edit Entry Form (Hidden by default) -->
                                    <tr id="edit-form-{{ $entry->id }}" class="hidden bg-gray-50 dark:bg-gray-700">
                                        <td colspan="6" class="px-6 py-4">
                                            <form action="{{ route('user-progress.update', $entry->game->id) }}" method="POST" class="space-y-4 entry-edit-form">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="game_id" value="{{ $entry->game->id }}">
                                                <!-- This form submits to: {{ route('user-progress.update', $entry->game->id) }} -->
                                                <!-- Game ID: {{ $entry->game->id }} -->
                                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-{{ ($vnList->type === 'custom' || $vnList->type === 'completed') ? '3' : '2' }} gap-4">
                                                    <div>
                                                        <label for="game_version_id-{{ $entry->id }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Last Read Version</label>
                                                        <select id="game_version_id-{{ $entry->id }}" name="game_version_id" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md py-2 px-3">
                                                            <option value="">Not started</option>
                                                            @foreach ($entry->game->gameVersions()->orderByDesc('published_at')->get() as $version)
                                                                <option value="{{ $version->id }}" {{ $userProgress?->game_version_id == $version->id ? 'selected' : '' }}>
                                                                    {{ $version->version }} ({{ $version->published_at->format('Y-m-d') }})
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>

                                                    <div>
                                                        <label for="started_at-{{ $entry->id }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Started At</label>
                                                        <div class="relative">
                                                            <input type="date" id="started_at-{{ $entry->id }}" name="started_at" value="{{ $userProgress?->started_at ? $userProgress->started_at->format('Y-m-d') : '' }}" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md py-2 px-3 date-input">
                                                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 mt-1">
                                                                <svg class="h-5 w-5 text-gray-500 dark:text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                                                                </svg>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    @if ($vnList->type === 'custom' || $vnList->type === 'completed')
                                                        <div>
                                                            <label for="completed_at-{{ $entry->id }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Completed At</label>
                                                            <div class="relative">
                                                                <input type="date" id="completed_at-{{ $entry->id }}" name="completed_at" value="{{ $userProgress?->completed_at ? $userProgress->completed_at->format('Y-m-d') : '' }}" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md py-2 px-3 date-input">
                                                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 mt-1">
                                                                    <svg class="h-5 w-5 text-gray-500 dark:text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                                        <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                                                                    </svg>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>

                                                <div>
                                                    <label for="personal_notes-{{ $entry->id }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
                                                    <textarea id="personal_notes-{{ $entry->id }}" name="personal_notes" rows="6" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md py-2 px-3">{{ $userProgress?->personal_notes }}</textarea>
                                                </div>

                                                <div class="flex justify-end space-x-2">
                                                    <button type="button" onclick="toggleEditForm('{{ $entry->id }}')" class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                        Cancel
                                                    </button>
                                                    <button type="submit" class="inline-flex items-center px-3 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                        Save Changes
                                                    </button>
                                                </div>
                                            </form>
                                        </td>
                                    </tr>

                                    <!-- Move Entry Form (Hidden by default) -->
                                    <tr id="move-form-{{ $entry->id }}" class="hidden bg-gray-50 dark:bg-gray-700">
                                        <td colspan="6" class="px-6 py-4">
                                            <form action="{{ route('list-entries.move', $entry) }}" method="POST" class="move-entry-form">
                                                @csrf

                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div>
                                                        <label for="target_list_id-{{ $entry->id }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Target List</label>
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

                                                        <select id="target_list_id-{{ $entry->id }}" name="target_list_id" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md py-2 px-3">
                                                            @foreach ($availableLists as $targetList)
                                                                <option value="{{ $targetList->id }}">
                                                                    {{ $targetList->name }} ({{ ucfirst(str_replace('_', ' ', $targetList->type)) }})
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
                                                    <button type="button" onclick="toggleMoveForm('{{ $entry->id }}')" class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                        Cancel
                                                    </button>
                                                    <button type="submit" class="inline-flex items-center px-3 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                                                        Move to List
                                                    </button>
                                                </div>
                                            </form>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @include('components.version-comparison-dialog')

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
        }

        function toggleMoveForm(entryId) {
            const moveForm = document.getElementById('move-form-' + entryId);
            moveForm.classList.toggle('hidden');
        }
    </script>

    @if ($isOwner)
    <script>
        // Initialize Sortable.js for drag and drop reordering
        document.addEventListener('DOMContentLoaded', function() {
            const entriesList = document.getElementById('entries-list');
            if (entriesList) {
                new Sortable(entriesList, {
                    animation: 150,
                    handle: '.cursor-move',
                    ghostClass: 'sortable-ghost',
                    dragClass: 'sortable-drag',
                    onEnd: async function(evt) {
                        const ids = Array.from(entriesList.children)
                            .filter(el => el.matches('tr:not([id^="edit-form-"]):not([id^="move-form-"])')) // Skip the edit and move form rows
                            .map(el => el.dataset.id);

                        try {
                            const response = await fetch('{{ route('vn-lists.update-order', $vnList) }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({ entries: ids })
                            });

                            const data = await response.json();

                            if (!data.success) {
                                console.error('Failed to update order:', data.message);
                            }
                        } catch (error) {
                            console.error('Error updating order:', error);
                        }
                    }
                });
            }

            // Set up event handlers for forms
            document.querySelectorAll('.entry-edit-form').forEach(form => {
                form.addEventListener('submit', async function(e) {
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

            document.querySelectorAll('.move-entry-form').forEach(form => {
                form.addEventListener('submit', async function(e) {
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
                form.addEventListener('submit', async function(e) {
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

            document.querySelectorAll('.toggle-visibility-form').forEach(form => {
                form.addEventListener('submit', async function(e) {
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
                            console.error('Error toggling visibility:', data.message);
                            alert('Failed to toggle visibility: ' + data.message);
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
