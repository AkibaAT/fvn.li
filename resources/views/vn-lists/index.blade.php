<x-layouts.app :metaTags="$metaTags">
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Your Visual Novel Lists</h1>
            <div class="flex space-x-2">
                <a href="{{ route('vn-lists.public') }}" class="inline-flex items-center px-4 py-2 bg-purple-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-400 active:bg-purple-600 focus:outline-none focus:border-purple-600 focus:ring focus:ring-purple-200 transition">
                    Public Lists
                </a>
                <a href="{{ route('vn-lists.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-400 active:bg-blue-600 focus:outline-none focus:border-blue-600 focus:ring focus:ring-blue-200 transition">
                    New List
                </a>
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

        <div class="mb-6 flex flex-wrap gap-2">
            <a href="{{ route('vn-lists.index') }}" class="px-3 py-1 rounded-full {{ !request('visibility') ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                All Lists
            </a>
            <a href="{{ route('vn-lists.index', ['visibility' => 'public']) }}" class="px-3 py-1 rounded-full {{ request('visibility') === 'public' ? 'bg-purple-500 text-white' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                Public Lists
            </a>
            <a href="{{ route('vn-lists.index', ['visibility' => 'private']) }}" class="px-3 py-1 rounded-full {{ request('visibility') === 'private' ? 'bg-gray-500 text-white' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                Private Lists
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ($lists as $list)
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg shadow p-4 border-l-4 border-{{ $list->type === 'reading' ? 'blue' : ($list->type === 'completed' ? 'green' : ($list->type === 'plan_to_read' ? 'yellow' : ($list->type === 'on_hold' ? 'orange' : ($list->type === 'dropped' ? 'red' : 'gray')))) }}-500">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <h2 class="text-xl font-bold text-gray-800 dark:text-white">{{ $list->name }}</h2>
                            <div class="mt-1 flex items-center space-x-2">
                                {{-- Public tag moved to top-right --}}
                            </div>
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

                    @if ($list->description)
                        <p class="text-gray-600 dark:text-gray-300 text-sm mb-3">{{ $list->description }}</p>
                    @endif

                    <div class="flex items-center justify-between">
                        <p class="text-gray-500 dark:text-gray-400 text-sm">
                            {{ $list->entries->count() }} {{ Str::plural('entry', $list->entries->count()) }}
                        </p>

                        <div class="flex space-x-2">
                            <a href="{{ route('vn-lists.show', $list) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                View
                            </a>

                            <form action="{{ route('vn-lists.toggle-visibility', $list) }}" method="POST" class="inline toggle-visibility-form">
                                @csrf
                                <button type="submit" class="{{ $list->is_public ? 'text-purple-600 hover:text-purple-800 dark:text-purple-400 dark:hover:text-purple-300' : 'text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-300' }}">
                                    {{ $list->is_public ? 'Make Private' : 'Make Public' }}
                                </button>
                            </form>

                            @unless ($list->is_default)
                                <a href="{{ route('vn-lists.edit', $list) }}" class="text-yellow-600 hover:text-yellow-800 dark:text-yellow-400 dark:hover:text-yellow-300">
                                    Edit
                                </a>

                                <form action="{{ route('vn-lists.destroy', $list) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this list?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">
                                        Delete
                                    </button>
                                </form>
                            @endunless
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.toggle-visibility-form').forEach(form => {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();

                    const button = this.querySelector('button');
                    const originalText = button.textContent;
                    button.disabled = true;

                    try {
                        const response = await fetch(this.action, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: new FormData(this)
                        });

                        const data = await response.json();

                        if (data.success) {
                            // Update the button text and style without page reload
                            const newIsPublic = !button.textContent.includes('Private');
                            button.textContent = newIsPublic ? 'Make Private' : 'Make Public';
                            button.className = newIsPublic ?
                                'text-purple-600 hover:text-purple-800 dark:text-purple-400 dark:hover:text-purple-300' :
                                'text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-300';

                            // Find the parent list item and update the public tag
                            const listItem = button.closest('.bg-gray-50');
                            if (listItem) {
                                const tagsContainer = listItem.querySelector('.flex.items-center.space-x-2');
                                if (tagsContainer) {
                                    const existingPublicTag = tagsContainer.querySelector('.bg-purple-100');

                                    if (newIsPublic && !existingPublicTag) {
                                        const publicTag = document.createElement('span');
                                        publicTag.className = 'px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200';
                                        publicTag.textContent = 'Public';
                                        tagsContainer.appendChild(publicTag);
                                    } else if (!newIsPublic && existingPublicTag) {
                                        existingPublicTag.remove();
                                    }
                                }
                            }
                        } else {
                            throw new Error(data.message || 'Failed to toggle visibility');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert(error.message);
                        button.textContent = originalText;
                    } finally {
                        button.disabled = false;
                    }
                });
            });
        });
    </script>
</x-layouts.app>
