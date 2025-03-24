@props(['list', 'isOwner' => false])

<div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden border-l-4 border-{{ $list->type === 'reading' ? 'blue' : ($list->type === 'completed' ? 'green' : ($list->type === 'plan_to_read' ? 'yellow' : ($list->type === 'on_hold' ? 'orange' : ($list->type === 'dropped' ? 'red' : 'gray')))) }}-500 flex flex-col h-full">
    <div class="p-5 flex-grow">
        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-1">
                    <a href="{{ route('vn-lists.show', $list) }}" class="hover:text-blue-600 dark:hover:text-blue-400">
                        {{ $list->name }}
                    </a>
                </h2>
                @if (!$isOwner && isset($list->user))
                <div class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                    By <a href="{{ route('vn-lists.user-public', $list->user) }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $list->user->name }}</a>
                </div>
                @endif
            </div>
            <div class="flex items-center space-x-2">
                @if (!$list->is_default)
                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-{{ $list->type === 'reading' ? 'blue' : ($list->type === 'completed' ? 'green' : ($list->type === 'plan_to_read' ? 'yellow' : ($list->type === 'on_hold' ? 'orange' : ($list->type === 'dropped' ? 'red' : 'gray')))) }}-100 text-{{ $list->type === 'reading' ? 'blue' : ($list->type === 'completed' ? 'green' : ($list->type === 'plan_to_read' ? 'yellow' : ($list->type === 'on_hold' ? 'orange' : ($list->type === 'dropped' ? 'red' : 'gray')))) }}-800 dark:bg-{{ $list->type === 'reading' ? 'blue' : ($list->type === 'completed' ? 'green' : ($list->type === 'plan_to_read' ? 'yellow' : ($list->type === 'on_hold' ? 'orange' : ($list->type === 'dropped' ? 'red' : 'gray')))) }}-900 dark:text-{{ $list->type === 'reading' ? 'blue' : ($list->type === 'completed' ? 'green' : ($list->type === 'plan_to_read' ? 'yellow' : ($list->type === 'on_hold' ? 'orange' : ($list->type === 'dropped' ? 'red' : 'gray')))) }}-200">
                    {{ ucfirst(str_replace('_', ' ', $list->type)) }}
                </span>
                @endif
                @if ($list->is_public)
                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                    Public
                </span>
                @endif
            </div>
        </div>

        <p class="text-gray-600 dark:text-gray-300 text-sm mt-2">
            {!! $list->description ? nl2br(e(Str::limit($list->description, 100))) : 'No description available.' !!}
        </p>
    </div>

    @if ($list->entries->isNotEmpty())
        <div class="border-t border-gray-200 dark:border-gray-700 px-5 py-3">
            <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-2">Featured Games ({{ $list->entries->count() }})</h3>
            <div class="flex flex-wrap gap-2">
                @foreach ($list->entries->take(3) as $entry)
                    <a href="{{ route('games.show', $entry->game->slug) }}" class="text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200">
                        {{ Str::limit($entry->game->name, 25) }}
                    </a>
                @endforeach
                @if ($list->entries->count() > 3)
                    <span class="text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded text-gray-800 dark:text-gray-200">
                        +{{ $list->entries->count() - 3 }} more
                    </span>
                @endif
            </div>
        </div>
    @endif

    @if ($isOwner)
    <div class="border-t border-gray-200 dark:border-gray-700 px-5 py-3 mt-auto">
        <div class="flex flex-nowrap items-center space-x-4">
            <a href="{{ route('vn-lists.show', $list) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 text-sm whitespace-nowrap">
                View
            </a>

            <form action="{{ route('vn-lists.toggle-visibility', $list) }}" method="POST" class="inline-flex toggle-visibility-form">
                @csrf
                <button type="submit" class="{{ $list->is_public ? 'text-purple-600 hover:text-purple-800 dark:text-purple-400 dark:hover:text-purple-300' : 'text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-300' }} text-sm whitespace-nowrap">
                    {{ $list->is_public ? 'Make Private' : 'Make Public' }}
                </button>
            </form>

            <a href="{{ route('vn-lists.edit', $list) }}" class="text-yellow-600 hover:text-yellow-800 dark:text-yellow-400 dark:hover:text-yellow-300 text-sm whitespace-nowrap">
                Edit
            </a>

            @unless ($list->is_default)
                <form action="{{ route('vn-lists.destroy', $list) }}" method="POST" class="inline-flex" onsubmit="return confirm('Are you sure you want to delete this list?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 text-sm whitespace-nowrap">
                        Delete
                    </button>
                </form>
            @endunless
        </div>
    </div>
    @endif
</div> 