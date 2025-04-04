<x-layouts.app :metaTags="$metaTags">
    <div class="container mx-auto">
        {{-- Header Card --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 md:p-6 border-l-4 border-blue-500 mb-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">Digest Notifications</h1>
                    <p class="text-gray-600 dark:text-gray-300 mt-2">Game updates from {{ \Carbon\Carbon::parse($date)->format('F j, Y') }}</p>
                </div>
                <div>
                    <a href="{{ route('user.dashboard.show') }}" class="inline-flex items-center px-3 py-1 bg-gray-200 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-800 dark:text-white uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-gray-600 active:bg-gray-400 dark:active:bg-gray-500 focus:outline-none focus:border-gray-400 dark:focus:border-gray-500 focus:ring focus:ring-gray-200 dark:focus:ring-gray-700 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        {{-- List Stats Card --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm md:pl-7 md:pr-6 p-4 mb-4">
            <div class="flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    Updates ({{ $notifications->count() }})
                </h2>
            </div>
        </div>

        @if ($notifications->isEmpty())
            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-8 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                </svg>
                <h3 class="mt-2 text-lg font-medium text-gray-900 dark:text-white">No Digest Notifications</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">You don't have any digest notifications for this date.</p>
            </div>
        @else
            {{-- Desktop Table Header - Visible only on lg screens and up --}}
            <div class="hidden lg:flex bg-gray-100 dark:bg-gray-700 rounded-t-lg p-3 pr-5 font-medium text-sm text-gray-500 dark:text-gray-300 uppercase">
                <div class="w-8"></div> {{-- Spacer --}}
                <div class="w-20 mr-2"></div> {{-- Thumbnail space --}}
                <div class="flex-grow">Title</div>
                <div class="w-52">Version</div>
                <div class="w-30">Updated</div>
                <div class="w-30">List</div>
            </div>

            {{-- Entry List --}}
            <div id="entries-list" class="space-y-3 text-gray-700 dark:text-gray-300 md:grid md:grid-cols-2 md:gap-3 md:space-y-0 lg:block lg:space-y-3">
                @foreach ($notifications as $digestType => $digestNotifications)
                    @foreach ($digestNotifications as $notification)
                        @php
                            $game = $notification->game->load(['latestVersion']);
                            $gameVersion = $notification->gameVersion; // This is the NEW version
                            // Find the user's progress to determine the PREVIOUS version they were on
                            $userProgressRecord = $userGameProgress[$game->id] ?? null;
                            $previousVersion = $userProgressRecord?->gameVersion ?? null;

                            // Determine if a comparison is possible and makes sense
                            $canCompare = $previousVersion && $previousVersion->id !== $gameVersion->id;
                        @endphp

                        <div class="bg-white dark:bg-gray-800 rounded-lg md:rounded-lg lg:rounded-none shadow-sm">
                            {{-- Desktop View --}}
                            <div class="hidden lg:flex items-center p-3 pr-5">
                                <div class="w-8"></div> {{-- Spacer for alignment --}}

                                {{-- Thumbnail --}}
                                <div class="w-20 mr-2">
                                    <a href="{{ route('games.show', $game->slug) }}">
                                        <x-game-thumbnail :game="$game" variant="small" class="w-18 h-18 object-cover rounded"/>
                                    </a>
                                </div>

                                {{-- Title --}}
                                <div class="flex-grow">
                                    <a href="{{ route('games.show', $game->slug) }}" class="font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ $game->name }}
                                    </a>
                                    @if ($userProgressRecord?->personal_notes)
                                        <div class="text-xs italic truncate max-w-md">
                                            "{{ $userProgressRecord->personal_notes }}"
                                        </div>
                                    @endif
                                </div>

                                {{-- Version --}}
                                <div class="w-52">
                                    <div class="border-l-4 pl-3 border-yellow-500">
                                        v{{ $gameVersion->version }}
                                        <span class="text-gray-400">
                                            ({{ $gameVersion->published_at->format('Y-m-d') }})
                                        </span>

                                        @if ($canCompare)
                                            @include('games.components.version-info-block', [
                                                'currentVersion' => $previousVersion,
                                                'game' => $game,
                                                'layout' => 'desktop'
                                            ])
                                        @else
                                            <div class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                                Released: {{ $gameVersion->published_at->format('M d, Y') }}
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                {{-- Updated Date --}}
                                <div class="w-30 text-sm">
                                    {{ $notification->created_at->format('M d, Y') }}
                                </div>

                                {{-- List Type --}}
                                <div class="w-30 text-sm">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full
                                        bg-{{ $digestType === 'reading' ? 'blue' : ($digestType === 'completed' ? 'green' : ($digestType === 'plan_to_read' ? 'yellow' : ($digestType === 'on_hold' ? 'orange' : ($digestType === 'dropped' ? 'red' : 'gray')))) }}-100
                                        text-{{ $digestType === 'reading' ? 'blue' : ($digestType === 'completed' ? 'green' : ($digestType === 'plan_to_read' ? 'yellow' : ($digestType === 'on_hold' ? 'orange' : ($digestType === 'dropped' ? 'red' : 'gray')))) }}-800
                                        dark:bg-{{ $digestType === 'reading' ? 'blue' : ($digestType === 'completed' ? 'green' : ($digestType === 'plan_to_read' ? 'yellow' : ($digestType === 'on_hold' ? 'orange' : ($digestType === 'dropped' ? 'red' : 'gray')))) }}-900
                                        dark:text-{{ $digestType === 'reading' ? 'blue' : ($digestType === 'completed' ? 'green' : ($digestType === 'plan_to_read' ? 'yellow' : ($digestType === 'on_hold' ? 'orange' : ($digestType === 'dropped' ? 'red' : 'gray')))) }}-200">
                                        {{ ucfirst(str_replace('_', ' ', $digestType)) }}
                                    </span>
                                </div>
                            </div>

                            {{-- Mobile/Tablet View --}}
                            <div class="flex lg:hidden p-4">
                                <div class="flex gap-4">
                                    {{-- Thumbnail --}}
                                    <a href="{{ route('games.show', $game->slug) }}">
                                        <x-game-thumbnail :game="$game" variant="small" class="w-32 h-32 object-cover rounded"/>
                                    </a>

                                    {{-- Game Info --}}
                                    <div class="flex-1">
                                        <a href="{{ route('games.show', $game->slug) }}" class="text-lg font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                            {{ $game->name }}
                                        </a>

                                        <div class="flex items-center gap-2 mt-2">
                                            {{-- Version Badge --}}
                                            <span class="text-xs px-2 py-1 rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300">
                                                v{{ $gameVersion->version }}
                                            </span>

                                            {{-- List Type Badge --}}
                                            <span class="text-xs px-2 py-1 rounded-full
                                                bg-{{ $digestType === 'reading' ? 'blue' : ($digestType === 'completed' ? 'green' : ($digestType === 'plan_to_read' ? 'yellow' : ($digestType === 'on_hold' ? 'orange' : ($digestType === 'dropped' ? 'red' : 'gray')))) }}-100
                                                text-{{ $digestType === 'reading' ? 'blue' : ($digestType === 'completed' ? 'green' : ($digestType === 'plan_to_read' ? 'yellow' : ($digestType === 'on_hold' ? 'orange' : ($digestType === 'dropped' ? 'red' : 'gray')))) }}-800
                                                dark:bg-{{ $digestType === 'reading' ? 'blue' : ($digestType === 'completed' ? 'green' : ($digestType === 'plan_to_read' ? 'yellow' : ($digestType === 'on_hold' ? 'orange' : ($digestType === 'dropped' ? 'red' : 'gray')))) }}-900
                                                dark:text-{{ $digestType === 'reading' ? 'blue' : ($digestType === 'completed' ? 'green' : ($digestType === 'plan_to_read' ? 'yellow' : ($digestType === 'on_hold' ? 'orange' : ($digestType === 'dropped' ? 'red' : 'gray')))) }}-200">
                                                {{ ucfirst(str_replace('_', ' ', $digestType)) }}
                                            </span>
                                        </div>

                                        <div class="text-sm mt-2">
                                            {{-- Release Date --}}
                                            <div>
                                                <span>Released:</span>
                                                <span class="ml-1 text-gray-500 dark:text-gray-400">
                                                    {{ $gameVersion->published_at->format('M d, Y') }}
                                                </span>
                                            </div>

                                            {{-- Updated Date --}}
                                            <div>
                                                <span>Updated:</span>
                                                <span class="ml-1 text-gray-500 dark:text-gray-400">
                                                    {{ $notification->created_at->format('M d, Y') }}
                                                </span>
                                            </div>

                                            @if ($canCompare)
                                                @include('games.components.version-info-block', [
                                                    'currentVersion' => $previousVersion,
                                                    'game' => $game,
                                                    'layout' => 'mobile'
                                                ])
                                            @endif

                                            {{-- Notes Preview (truncated) --}}
                                            @if ($userProgressRecord?->personal_notes)
                                                <div class="mt-1 text-xs italic line-clamp-1">
                                                    "{{ $userProgressRecord->personal_notes }}"
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endforeach
            </div>
        @endif
    </div>

    <livewire:components.version-comparison />

    <style>
        /* Custom class to display line-clamping (truncated text with ellipsis) */
        .line-clamp-1 {
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>

    @push('scripts')
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
        </script>
    @endpush
</x-layouts.app>
