<x-layouts.app :metaTags="$metaTags">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $metaTags['title'] ?? 'Edit Game' }}</h1>
        <a href="{{ route('user.games.index') }}" class="text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">
            ← Back to My Games
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Game Information -->
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Game Information</h2>

                <div class="space-y-4">
                    @if ($game->thumb_url)
                        <div>
                            <img src="{{ $game->thumb_url }}" alt="{{ $game->name }}" class="w-full rounded-lg">
                        </div>
                    @endif

                    <div>
                        <h3 class="font-medium text-gray-900 dark:text-white">{{ $game->name }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $game->status }}</p>
                    </div>

                    @if ($game->description)
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ Str::limit($game->description, 200) }}</p>
                        </div>
                    @endif

                    <div class="flex gap-2">
                        <a href="{{ $game->url }}" target="_blank" class="inline-flex items-center gap-1 px-3 py-1 bg-gray-600 text-white text-sm rounded hover:bg-gray-700 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                            itch.io
                        </a>
                        <a href="{{ route('games.show', $game->slug) }}" class="inline-flex items-center gap-1 px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            View on Site
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Form -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Downloads</h2>

                <form action="{{ route('user.games.update', $game) }}" method="POST" id="links-form">
                    @csrf
                    @method('PUT')

                    <div class="space-y-4">
                        <div id="links-container">
                            @php
                                $existingLinks = old('links', $game->additional_links ?? []);
                                if (empty($existingLinks)) {
                                    $existingLinks = [['id' => '', 'name' => '', 'url' => '', 'platform' => '']];
                                }
                            @endphp

                            @foreach ($existingLinks as $index => $link)
                                <div class="link-item border border-gray-200 dark:border-gray-600 rounded-lg p-4" data-index="{{ $index }}">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex items-center gap-2">
                                            <svg class="drag-handle cursor-move h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                                            </svg>
                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Link {{ $index + 1 }}</span>
                                        </div>
                                        <button type="button" class="remove-link text-red-500 hover:text-red-700" title="Remove link">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </div>

                                    <input type="hidden" name="links[{{ $index }}][id]" value="{{ $link['id'] ?? '' }}">

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                Link Name <span class="text-red-500">*</span>
                                            </label>
                                            <input
                                                type="text"
                                                name="links[{{ $index }}][name]"
                                                value="{{ $link['name'] ?? '' }}"
                                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                                placeholder="e.g., Direct Download, Mirror Link"
                                                required
                                            >
                                            @error("links.{$index}.name")
                                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                Platform
                                            </label>
                                            <select
                                                name="links[{{ $index }}][platform]"
                                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                            >
                                                <option value="">Select Platform</option>
                                                @foreach ($platforms as $key => $label)
                                                    <option value="{{ $key }}" {{ ($link['platform'] ?? '') === $key ? 'selected' : '' }}>
                                                        {{ $label }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="mt-3">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            URL <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="url"
                                            name="links[{{ $index }}][url]"
                                            value="{{ $link['url'] ?? '' }}"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                            placeholder="https://example.com/download"
                                            required
                                        >
                                        @error("links.{$index}.url")
                                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    @if (!empty($link['last_edited_at']))
                                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                            Last edited: {{ \Carbon\Carbon::parse($link['last_edited_at'])->diffForHumans() }}
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <div class="flex justify-between items-center">
                            <button type="button" id="add-link" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Add Link
                            </button>
                            <span class="text-sm text-gray-500 dark:text-gray-400">Maximum 15 links</span>
                        </div>

                        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                            <div class="flex">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-yellow-400 mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                                <div>
                                    <h4 class="text-sm font-medium text-yellow-800 dark:text-yellow-300">Important Notes</h4>
                                    <ul class="mt-1 text-sm text-yellow-700 dark:text-yellow-400 list-disc list-inside space-y-1">
                                        <li>These links will be displayed publicly on your game's page</li>
                                        <li>Make sure all links are accessible and lead to safe downloads</li>
                                        <li>You are responsible for maintaining the availability of these links</li>
                                        <li>Use drag handles to reorder links</li>
                                        <li>Remove all links to disable the downloads section</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                Save Changes
                            </button>
                            <a href="{{ route('user.games.index') }}" class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500 transition-colors">
                                Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('links-container');
            const addButton = document.getElementById('add-link');
            let linkIndex = {{ count($existingLinks) }};

            // Platform options for new links
            const platformOptions = {!! json_encode($platforms) !!};

            // Add new link
            addButton.addEventListener('click', function() {
                if (container.children.length >= 15) {
                    alert('Maximum 15 links allowed');
                    return;
                }

                // Update button state
                if (container.children.length >= 14) {
                    addButton.disabled = true;
                    addButton.classList.add('opacity-50', 'cursor-not-allowed');
                }

                const linkHtml = createLinkHtml(linkIndex, '', '', '', '');
                container.insertAdjacentHTML('beforeend', linkHtml);
                linkIndex++;
                updateLinkNumbers();
                attachEventListeners();
            });

            // Create link HTML
            function createLinkHtml(index, id, name, url, platform, lastEditedAt = null) {
                let platformOptionsHtml = '<option value="">Select Platform</option>';
                for (const [key, label] of Object.entries(platformOptions)) {
                    const selected = platform === key ? 'selected' : '';
                    platformOptionsHtml += `<option value="${key}" ${selected}>${label}</option>`;
                }

                let lastEditedHtml = '';
                if (lastEditedAt) {
                    const editedDate = new Date(lastEditedAt);
                    const now = new Date();
                    const diffMs = now - editedDate;
                    const diffMins = Math.floor(diffMs / 60000);
                    const diffHours = Math.floor(diffMs / 3600000);
                    const diffDays = Math.floor(diffMs / 86400000);

                    let timeAgo;
                    if (diffMins < 1) {
                        timeAgo = 'just now';
                    } else if (diffMins < 60) {
                        timeAgo = `${diffMins} minute${diffMins === 1 ? '' : 's'} ago`;
                    } else if (diffHours < 24) {
                        timeAgo = `${diffHours} hour${diffHours === 1 ? '' : 's'} ago`;
                    } else {
                        timeAgo = `${diffDays} day${diffDays === 1 ? '' : 's'} ago`;
                    }

                    lastEditedHtml = `
                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Last edited: ${timeAgo}
                        </div>
                    `;
                }

                return `
                    <div class="link-item border border-gray-200 dark:border-gray-600 rounded-lg p-4" data-index="${index}">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <svg class="drag-handle cursor-move h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                                </svg>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Link ${index + 1}</span>
                            </div>
                            <button type="button" class="remove-link text-red-500 hover:text-red-700" title="Remove link">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>

                        <input type="hidden" name="links[${index}][id]" value="${id}">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Link Name <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    name="links[${index}][name]"
                                    value="${name}"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                    placeholder="e.g., Direct Download, Mirror Link"
                                    required
                                >
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Platform
                                </label>
                                <select
                                    name="links[${index}][platform]"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                >
                                    ${platformOptionsHtml}
                                </select>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                URL <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="url"
                                name="links[${index}][url]"
                                value="${url}"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                placeholder="https://example.com/download"
                                required
                            >
                        </div>

                        ${lastEditedHtml}
                    </div>
                `;
            }

            // Remove link
            function attachEventListeners() {
                document.querySelectorAll('.remove-link').forEach(button => {
                    button.addEventListener('click', function() {
                        if (container.children.length <= 1) {
                            // Keep at least one link item, but clear its values
                            const linkItem = this.closest('.link-item');
                            linkItem.querySelectorAll('input[type="text"], input[type="url"]').forEach(input => input.value = '');
                            linkItem.querySelector('select').selectedIndex = 0;
                        } else {
                            this.closest('.link-item').remove();
                            updateLinkNumbers();
                        }
                    });
                });
            }

            // Update link numbers
            function updateLinkNumbers() {
                document.querySelectorAll('.link-item').forEach((item, index) => {
                    item.querySelector('span').textContent = `Link ${index + 1}`;

                    // Update input names
                    item.querySelectorAll('input, select').forEach(input => {
                        const name = input.name;
                        if (name && name.includes('[')) {
                            const newName = name.replace(/\[\d+\]/, `[${index}]`);
                            input.name = newName;
                        }
                    });
                });

                // Update add button state
                const linkCount = container.children.length;
                if (linkCount >= 15) {
                    addButton.disabled = true;
                    addButton.classList.add('opacity-50', 'cursor-not-allowed');
                } else {
                    addButton.disabled = false;
                    addButton.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            }

            // Initial event listeners
            attachEventListeners();

            // Simple drag and drop reordering
            let draggedElement = null;

            container.addEventListener('dragstart', function(e) {
                if (e.target.classList.contains('drag-handle')) {
                    draggedElement = e.target.closest('.link-item');
                    draggedElement.style.opacity = '0.5';
                }
            });

            container.addEventListener('dragend', function(e) {
                if (draggedElement) {
                    draggedElement.style.opacity = '';
                    draggedElement = null;
                    updateLinkNumbers();
                }
            });

            container.addEventListener('dragover', function(e) {
                e.preventDefault();
            });

            container.addEventListener('drop', function(e) {
                e.preventDefault();
                if (draggedElement) {
                    const afterElement = getDragAfterElement(container, e.clientY);
                    if (afterElement == null) {
                        container.appendChild(draggedElement);
                    } else {
                        container.insertBefore(draggedElement, afterElement);
                    }
                }
            });

            // Make drag handles draggable
            document.querySelectorAll('.drag-handle').forEach(handle => {
                handle.closest('.link-item').draggable = true;
            });

            function getDragAfterElement(container, y) {
                const draggableElements = [...container.querySelectorAll('.link-item:not(.dragging)')];

                return draggableElements.reduce((closest, child) => {
                    const box = child.getBoundingClientRect();
                    const offset = y - box.top - box.height / 2;

                    if (offset < 0 && offset > closest.offset) {
                        return { offset: offset, element: child };
                    } else {
                        return closest;
                    }
                }, { offset: Number.NEGATIVE_INFINITY }).element;
            }
        });
    </script>
</x-layouts.app>
