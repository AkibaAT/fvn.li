@props(['game', 'userLists' => null, 'publicLists' => null, 'compact' => false])

@auth
    <div class="space-y-4">
        <!-- Fixed Top Controls Row -->
        <div class="flex flex-wrap gap-2">
            <!-- Manage Lists Button -->
            <button
                type="button"
                id="open-list-dialog-{{ $game->id }}"
                class="{{ $compact ? 'px-2 py-1 text-xs' : 'px-4 py-2 text-sm' }} bg-blue-600 hover:bg-blue-700 text-white rounded-md transition-colors {{ $attributes->get('class') }}"
                onclick="document.getElementById('list-dialog-{{ $game->id }}').showModal()"
            >
                @if ($userLists && $userLists->isNotEmpty())
                    <span>Manage in Lists</span>
                @else
                    <span>Add to {{ $compact ? '' : 'My ' }}Lists</span>
                @endif
            </button>

            @unless ($compact)
                <!-- User Lists Toggle Button -->
                @if ($userLists && $userLists->isNotEmpty())
                    @php
                        // Determine the primary list type for color coding
                        $primaryListType = null;
                        $listTypeCounts = [];

                        foreach ($userLists as $list) {
                            if (!isset($listTypeCounts[$list->type])) {
                                $listTypeCounts[$list->type] = 0;
                            }
                            $listTypeCounts[$list->type]++;
                        }

                        // Priority order for list types
                        $priorityOrder = ['reading', 'completed', 'plan_to_read', 'on_hold', 'dropped'];

                        // Find the highest priority list type that exists
                        foreach ($priorityOrder as $type) {
                            if (isset($listTypeCounts[$type]) && $listTypeCounts[$type] > 0) {
                                $primaryListType = $type;
                                break;
                            }
                        }

                        // Set color based on primary list type
                        $badgeColor = 'blue';
                        if ($primaryListType === 'reading') {
                            $badgeColor = 'blue';
                        } elseif ($primaryListType === 'completed') {
                            $badgeColor = 'green';
                        } elseif ($primaryListType === 'plan_to_read') {
                            $badgeColor = 'yellow';
                        } elseif ($primaryListType === 'on_hold') {
                            $badgeColor = 'orange';
                        } elseif ($primaryListType === 'dropped') {
                            $badgeColor = 'red';
                        }
                    @endphp
                    <button
                        type="button"
                        class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 bg-gray-100 dark:bg-gray-800 rounded-md transition-colors flex items-center gap-2"
                        onclick="toggleUserLists('{{ $game->id }}')"
                    >
                        <span>My Lists</span>
                        <span class="px-1.5 py-0.5 text-xs font-medium rounded-full bg-{{ $badgeColor }}-100 text-{{ $badgeColor }}-800 dark:bg-{{ $badgeColor }}-900 dark:text-{{ $badgeColor }}-200">
                            {{ $userLists->count() }}
                        </span>
                        <svg id="user-lists-chevron-{{ $game->id }}" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                @endif

                <!-- Public Lists Toggle Button -->
                @if ($publicLists && $publicLists->isNotEmpty())
                    <button
                        type="button"
                        class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 bg-gray-100 dark:bg-gray-800 rounded-md transition-colors flex items-center gap-2"
                        onclick="togglePublicLists('{{ $game->id }}')"
                    >
                        <span>Public Lists</span>
                        <span class="px-1.5 py-0.5 text-xs font-medium rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                            {{ $publicLists->count() }}
                        </span>
                        <svg id="public-lists-chevron-{{ $game->id }}" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                @endif
            @endunless
        </div>

        @unless ($compact)
            <!-- User Lists Section -->
            @if ($userLists && $userLists->isNotEmpty())
                <div>
                    <!-- Expandable Content -->
                    <div id="user-lists-{{ $game->id }}" class="hidden mt-2">
                        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-6 border border-gray-200 dark:border-gray-700/50">
                            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-4">My Lists</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                @foreach ($userLists as $list)
                                    <div class="flex flex-col p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
                                        <div class="flex items-start justify-between gap-4">
                                            <div class="flex-1 min-w-0">
                                                <a href="{{ route('vn-lists.show', $list) }}" class="block font-medium text-gray-900 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-400 truncate">
                                                    {{ $list->name }}
                                                </a>
                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    {{ ucfirst(str_replace('_', ' ', $list->type)) }}
                                                    @if ($list->is_public)
                                                        <span class="ml-1 px-1.5 py-0.5 text-xs font-medium rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                                            Public
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                            <span class="flex-shrink-0 px-2 py-0.5 text-xs font-medium rounded-full bg-{{ $list->type === 'reading' ? 'blue' : ($list->type === 'completed' ? 'green' : ($list->type === 'plan_to_read' ? 'yellow' : ($list->type === 'on_hold' ? 'orange' : ($list->type === 'dropped' ? 'red' : 'gray')))) }}-100 text-{{ $list->type === 'reading' ? 'blue' : ($list->type === 'completed' ? 'green' : ($list->type === 'plan_to_read' ? 'yellow' : ($list->type === 'on_hold' ? 'orange' : ($list->type === 'dropped' ? 'red' : 'gray')))) }}-800 dark:bg-{{ $list->type === 'reading' ? 'blue' : ($list->type === 'completed' ? 'green' : ($list->type === 'plan_to_read' ? 'yellow' : ($list->type === 'on_hold' ? 'orange' : ($list->type === 'dropped' ? 'red' : 'gray')))) }}-900 dark:text-{{ $list->type === 'reading' ? 'blue' : ($list->type === 'completed' ? 'green' : ($list->type === 'plan_to_read' ? 'yellow' : ($list->type === 'on_hold' ? 'orange' : ($list->type === 'dropped' ? 'red' : 'gray')))) }}-200">
                                                {{ ucfirst(str_replace('_', ' ', $list->type)) }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Public Lists Section -->
            @if ($publicLists && $publicLists->isNotEmpty())
                <div>
                    <!-- Expandable Content -->
                    <div id="public-lists-{{ $game->id }}" class="hidden mt-2">
                        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-6 border border-gray-200 dark:border-gray-700/50">
                            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-4">Public Lists</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                @foreach ($publicLists as $list)
                                    <div class="flex flex-col p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
                                        <div class="flex items-start justify-between gap-4">
                                            <div class="flex-1 min-w-0">
                                                <a href="{{ route('vn-lists.show', $list) }}" class="block font-medium text-gray-900 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-400 truncate">
                                                    {{ $list->name }}
                                                </a>
                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    By <a href="{{ route('vn-lists.user-public', $list->user) }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $list->user->name }}</a>
                                                </div>
                                            </div>
                                            <span class="flex-shrink-0 px-2 py-0.5 text-xs font-medium rounded-full bg-{{ $list->type === 'reading' ? 'blue' : ($list->type === 'completed' ? 'green' : ($list->type === 'plan_to_read' ? 'yellow' : ($list->type === 'on_hold' ? 'orange' : ($list->type === 'dropped' ? 'red' : 'gray')))) }}-100 text-{{ $list->type === 'reading' ? 'blue' : ($list->type === 'completed' ? 'green' : ($list->type === 'plan_to_read' ? 'yellow' : ($list->type === 'on_hold' ? 'orange' : ($list->type === 'dropped' ? 'red' : 'gray')))) }}-800 dark:bg-{{ $list->type === 'reading' ? 'blue' : ($list->type === 'completed' ? 'green' : ($list->type === 'plan_to_read' ? 'yellow' : ($list->type === 'on_hold' ? 'orange' : ($list->type === 'dropped' ? 'red' : 'gray')))) }}-900 dark:text-{{ $list->type === 'reading' ? 'blue' : ($list->type === 'completed' ? 'green' : ($list->type === 'plan_to_read' ? 'yellow' : ($list->type === 'on_hold' ? 'orange' : ($list->type === 'dropped' ? 'red' : 'gray')))) }}-200">
                                                {{ ucfirst(str_replace('_', ' ', $list->type)) }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endunless
    </div>

    <!-- Dialog for List Management -->
    <dialog
        id="list-dialog-{{ $game->id }}"
        class="m-auto rounded-lg bg-white dark:bg-gray-800 p-6 shadow-xl w-full max-w-md dark:text-gray-100 backdrop:backdrop-blur-md"
    >
        <div>
            <x-ui.dialog-header title="Manage Lists for {!! $game->name !!}"/>

            {{-- Fixed height message area --}}
            <div id="ajax-message-{{ $game->id }}" class="h-6 text-sm text-center" aria-live="polite"></div>

            <div class="space-y-6">
                {{-- Default Lists --}}
                <div>
                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Default Lists</h4>
                    <div class="space-y-1">
                        @foreach (['plan_to_read', 'reading', 'completed', 'on_hold', 'dropped'] as $listType)
                            @php
                                $list = Auth::user()->vnLists()->where('type', $listType)->first();
                                $isInList = $list && $list->entries()->where('game_id', $game->id)->exists();
                            @endphp
                            <form
                                action="{{ route('games.add-to-list', $game) }}"
                                method="POST"
                                data-default-list-form
                                data-game-id="{{ $game->id }}"
                            >
                                @csrf
                                <input type="hidden" name="list_type" value="{{ $listType }}">
                                <button
                                    type="submit"
                                    data-default-list
                                    data-game-id="{{ $game->id }}"
                                    class="w-full text-left px-4 py-2 text-sm {{ $isInList ? 'bg-blue-600 text-white hover:bg-blue-700' : 'bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600' }} flex items-center justify-between"
                                >
                                    <div class="flex items-center gap-2">
                                        <span>{{ ucfirst(str_replace('_', ' ', $listType)) }}</span>
                                        @unless (in_array($listType, ['reading', 'completed', 'plan_to_read', 'on_hold', 'dropped']))
                                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-{{ $listType === 'reading' ? 'blue' : ($listType === 'completed' ? 'green' : ($listType === 'plan_to_read' ? 'yellow' : ($listType === 'on_hold' ? 'orange' : ($listType === 'dropped' ? 'red' : 'gray')))) }}-100 text-{{ $listType === 'reading' ? 'blue' : ($listType === 'completed' ? 'green' : ($listType === 'plan_to_read' ? 'yellow' : ($listType === 'on_hold' ? 'orange' : ($listType === 'dropped' ? 'red' : 'gray')))) }}-800 dark:bg-{{ $listType === 'reading' ? 'blue' : ($listType === 'completed' ? 'green' : ($listType === 'plan_to_read' ? 'yellow' : ($listType === 'on_hold' ? 'orange' : ($listType === 'dropped' ? 'red' : 'gray')))) }}-900 dark:text-{{ $listType === 'reading' ? 'blue' : ($listType === 'completed' ? 'green' : ($listType === 'plan_to_read' ? 'yellow' : ($listType === 'on_hold' ? 'orange' : ($listType === 'dropped' ? 'red' : 'gray')))) }}-200">
                                            {{ ucfirst(str_replace('_', ' ', $listType)) }}
                                        </span>
                                        @else
                                        <span class="w-3 h-3 rounded-full bg-{{ $listType === 'reading' ? 'blue' : ($listType === 'completed' ? 'green' : ($listType === 'plan_to_read' ? 'yellow' : ($listType === 'on_hold' ? 'orange' : ($listType === 'dropped' ? 'red' : 'gray')))) }}-500"></span>
                                        @endunless
                                        @if ($list && $list->is_public)
                                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                                Public
                                            </span>
                                        @endif
                                    </div>
                                    @if ($isInList)
                                        <span class="text-sm font-medium">Remove</span>
                                    @endif
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>

                {{-- Custom Lists --}}
                <div>
                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Custom Lists</h4>
                    <div id="custom-lists-{{ $game->id }}" class="space-y-1">
                        @php
                            $customLists = Auth::user()->vnLists()->where('is_default', false)->get()->sortBy('name');
                        @endphp
                        @foreach ($customLists as $list)
                            @php
                                $isInList = $list->entries()->where('game_id', $game->id)->exists();
                            @endphp
                            <form
                                action="{{ route('list-entries.add-to-custom', $list) }}"
                                method="POST"
                                data-custom-list-form
                                data-game-id="{{ $game->id }}"
                            >
                                @csrf
                                <input type="hidden" name="game_id" value="{{ $game->id }}">
                                <button
                                    type="submit"
                                    data-game-id="{{ $game->id }}"
                                    class="w-full text-left px-4 py-2 text-sm {{ $isInList ? 'bg-blue-600 text-white hover:bg-blue-700' : 'bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600' }} flex items-center justify-between"
                                >
                                    {{ $list->name }}
                                    @if ($isInList)
                                        <span class="text-sm font-medium">Remove</span>
                                    @endif
                                </button>
                            </form>
                        @endforeach
                    </div>

                    {{-- Quick List Creation Form --}}
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <form
                            class="quick-list-form"
                            action="{{ route('vn-lists.store') }}"
                            method="POST"
                        >
                            @csrf
                            <input type="hidden" name="game_id" value="{{ $game->id }}">
                            <div class="flex gap-2">
                                <input
                                    type="text"
                                    name="name"
                                    placeholder="New list name"
                                    class="flex-1 px-3 py-1 text-sm rounded-md bg-gray-100 dark:bg-gray-700 border-0 focus:ring-2 focus:ring-blue-500"
                                    required
                                >
                                <button
                                    type="submit"
                                    class="px-3 py-1 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
                                >
                                    Create & Add
                                </button>
                            </div>
                            <div class="flex items-center mt-2">
                                <input type="checkbox" name="is_public" id="is_public_{{ $game->id }}" value="1" class="rounded border-gray-300 dark:border-gray-600 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-gray-800 dark:focus:ring-blue-600">
                                <label for="is_public_{{ $game->id }}" class="ml-2 block text-xs text-gray-600 dark:text-gray-400">Make this list public</label>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <x-ui.dialog-footer/>
        </div>
    </dialog>
@else
    <div class="text-sm text-gray-600 dark:text-gray-400">
        <a href="{{ route('login') }}" class="text-blue-600 dark:text-blue-400 hover:underline">Log in</a> to track your reading progress
    </div>
@endauth
