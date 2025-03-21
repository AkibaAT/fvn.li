<x-layouts.app :metaTags="$metaTags">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $user->name }}'s Lists</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-1">Public visual novel lists shared by this user</p>
            </div>
            <a href="{{ route('vn-lists.public') }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                Back to all public lists
            </a>
        </div>

        @if ($lists->count() > 0)
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($lists as $list)
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden border-l-4 border-{{ $list->type === 'reading' ? 'blue' : ($list->type === 'completed' ? 'green' : ($list->type === 'plan_to_read' ? 'yellow' : ($list->type === 'on_hold' ? 'orange' : ($list->type === 'dropped' ? 'red' : 'gray')))) }}-500">
                        <div class="p-5">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-1">
                                        <a href="{{ route('vn-lists.show', $list) }}" class="hover:text-blue-600 dark:hover:text-blue-400">
                                            {{ $list->name }}
                                        </a>
                                    </h2>
                                </div>
                                @if (!$list->is_default)
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-{{ $list->type === 'reading' ? 'blue' : ($list->type === 'completed' ? 'green' : ($list->type === 'plan_to_read' ? 'yellow' : ($list->type === 'on_hold' ? 'orange' : ($list->type === 'dropped' ? 'red' : 'gray')))) }}-100 text-{{ $list->type === 'reading' ? 'blue' : ($list->type === 'completed' ? 'green' : ($list->type === 'plan_to_read' ? 'yellow' : ($list->type === 'on_hold' ? 'orange' : ($list->type === 'dropped' ? 'red' : 'gray')))) }}-800 dark:bg-{{ $list->type === 'reading' ? 'blue' : ($list->type === 'completed' ? 'green' : ($list->type === 'plan_to_read' ? 'yellow' : ($list->type === 'on_hold' ? 'orange' : ($list->type === 'dropped' ? 'red' : 'gray')))) }}-900 dark:text-{{ $list->type === 'reading' ? 'blue' : ($list->type === 'completed' ? 'green' : ($list->type === 'plan_to_read' ? 'yellow' : ($list->type === 'on_hold' ? 'orange' : ($list->type === 'dropped' ? 'red' : 'gray')))) }}-200">
                                    {{ ucfirst(str_replace('_', ' ', $list->type)) }}
                                </span>
                                @endif
                            </div>
                            <p class="text-gray-600 dark:text-gray-300 mt-2">
                                {{ $list->description ? Str::limit($list->description, 100) : 'No description available.' }}
                            </p>
                        </div>

                        @if ($list->entries->isNotEmpty())
                            <div class="border-t border-gray-200 dark:border-gray-700 px-5 py-3">
                                <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-2">Featured Games ({{ $list->entries->count() }})</h3>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($list->entries->take(5) as $entry)
                                        <a href="{{ route('games.show', $entry->game->slug) }}" class="text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200">
                                            {{ Str::limit($entry->game->name, 30) }}
                                        </a>
                                    @endforeach
                                    @if ($list->entries->count() > 5)
                                        <span class="text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded text-gray-800 dark:text-gray-200">
                                            +{{ $list->entries->count() - 5 }} more
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $lists->links() }}
            </div>
        @else
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">No Public Lists Available</h2>
                <p class="text-gray-600 dark:text-gray-400">This user hasn't shared any public lists yet.</p>
            </div>
        @endif
    </div>
</x-layouts.app> 