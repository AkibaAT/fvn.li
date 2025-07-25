<x-layouts.app :metaTags="$metaTags">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $metaTags['title'] ?? 'Manage My Games' }}</h1>
        <a href="{{ route('user.dashboard.show') }}" class="text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">
            ← Back to Dashboard
        </a>
    </div>

    <!-- Flash Messages -->
    @if (session('success'))
        <div class="mb-4 p-4 bg-green-100 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg text-green-700 dark:text-green-300">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 p-4 bg-red-100 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-red-700 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    @if (!$itchioUsername)
        <!-- No itch.io account connected -->
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
            <div class="text-center">
                <div class="mb-4">
                    @include('components.ui.icons.itchio', ['class' => 'h-16 w-16 mx-auto text-gray-400'])
                </div>
                <h2 class="text-xl font-semibold mb-2 text-gray-900 dark:text-white">Connect Your itch.io Account</h2>
                <p class="text-gray-600 dark:text-gray-400 mb-6">
                    To manage your games, you need to connect your itch.io account first. This will allow us to verify your ownership of games in your namespace.
                </p>
                <form action="{{ route('user.merge', ['provider' => 'itchio']) }}" method="POST">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                        @include('components.ui.icons.itchio', ['class' => 'h-5 w-5'])
                        <span>Connect itch.io Account</span>
                    </button>
                </form>
            </div>
        </div>
    @elseif ($games->isEmpty())
        <!-- No games found -->
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
            <div class="text-center">
                <div class="mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </div>
                <h2 class="text-xl font-semibold mb-2 text-gray-900 dark:text-white">No Games Found</h2>
                <p class="text-gray-600 dark:text-gray-400 mb-4">
                    We couldn't find any games in your itch.io namespace (<strong>{{ $itchioUsername }}.itch.io</strong>).
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Games must be visible in our database to appear here. If you have games that should be listed, they may need to be imported first.
                </p>
            </div>
        </div>
    @else
        <!-- Games list -->
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Your Games ({{ $games->count() }})
                    </h2>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        Namespace: <strong>{{ $itchioUsername }}.itch.io</strong>
                    </div>
                </div>

                <div class="space-y-4">
                    @foreach ($games as $game)
                        <div class="border dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    @if ($game->thumb_url)
                                        <img src="{{ $game->thumb_url }}" alt="{{ $game->name }}" class="w-16 h-16 rounded object-cover">
                                    @else
                                        <div class="w-16 h-16 rounded bg-gray-200 dark:bg-gray-600 flex items-center justify-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                    @endif
                                    <div>
                                        <h3 class="font-medium text-gray-900 dark:text-white">{{ $game->name }}</h3>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $game->status }}</p>
                                        @if ($game->hasAdditionalLinks())
                                            <p class="text-xs text-green-600 dark:text-green-400 mt-1">
                                                ✓ {{ count($game->additional_links) }} download {{ count($game->additional_links) === 1 ? 'link' : 'links' }} set
                                            </p>
                                        @else
                                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                                No download links
                                            </p>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <a href="{{ $game->url }}" target="_blank" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" title="View on itch.io">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </a>
                                    <a href="{{ route('games.show', $game->slug) }}" class="text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300" title="View on site">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>
                                    <a href="{{ route('user.games.edit', $game) }}" class="inline-flex items-center gap-1 px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        Edit
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</x-layouts.app>
