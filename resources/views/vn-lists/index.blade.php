<x-layouts.app :metaTags="$metaTags">
    <div class="flex justify-between items-center mt-3 mb-6">
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
            <x-vn-list-card :list="$list" :isOwner="true" />
        @endforeach
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
                                'text-purple-600 hover:text-purple-800 dark:text-purple-400 dark:hover:text-purple-300 text-sm' :
                                'text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-300 text-sm';

                            // Find the parent card and update the public tag
                            const listCard = button.closest('.bg-white') || button.closest('.dark\\:bg-gray-800');
                            if (listCard) {
                                const tagsContainer = listCard.querySelector('.flex.items-center.space-x-2');
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
