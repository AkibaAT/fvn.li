@props(['game', 'userLists' => null, 'publicLists' => null])

@auth
    <div class="flex items-center justify-between">
        <!-- Compact Button to Open Dialog -->
        <button
            type="button"
            id="open-list-dialog-{{ $game->id }}"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-md transition-colors"
            onclick="document.getElementById('list-dialog-{{ $game->id }}').showModal()"
        >
            @if ($userLists && $userLists->isNotEmpty())
                <span>Manage in Lists</span>
            @else
                <span>Add to My Lists</span>
            @endif
        </button>

        <!-- Public Lists Summary -->
        @if ($publicLists && $publicLists->isNotEmpty())
            <button
                type="button"
                class="text-blue-600 dark:text-blue-400 hover:underline text-sm flex items-center"
                onclick="document.getElementById('public-lists-{{ $game->id }}').classList.toggle('hidden')"
            >
                <span>{{ $publicLists->count() }} public {{ Str::plural('list', $publicLists->count()) }}</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
        @endif
    </div>

    <!-- Public Lists Collapsible Section -->
    @if ($publicLists && $publicLists->isNotEmpty())
        <div id="public-lists-{{ $game->id }}" class="mt-3 space-y-2 hidden">
            @foreach ($publicLists as $list)
                <div class="p-2 bg-gray-50 dark:bg-gray-700 rounded-md">
                    <div class="flex justify-between items-start">
                        <div>
                            <a href="{{ route('vn-lists.show', $list) }}" class="font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                {{ $list->name }}
                            </a>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                By <a href="{{ route('vn-lists.user-public', $list->user) }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $list->user->name }}</a>
                            </div>
                        </div>
                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-{{ $list->type === 'reading' ? 'blue' : ($list->type === 'completed' ? 'green' : ($list->type === 'plan_to_read' ? 'yellow' : ($list->type === 'on_hold' ? 'orange' : ($list->type === 'dropped' ? 'red' : 'gray')))) }}-100 text-{{ $list->type === 'reading' ? 'blue' : ($list->type === 'completed' ? 'green' : ($list->type === 'plan_to_read' ? 'yellow' : ($list->type === 'on_hold' ? 'orange' : ($list->type === 'dropped' ? 'red' : 'gray')))) }}-800 dark:bg-{{ $list->type === 'reading' ? 'blue' : ($list->type === 'completed' ? 'green' : ($list->type === 'plan_to_read' ? 'yellow' : ($list->type === 'on_hold' ? 'orange' : ($list->type === 'dropped' ? 'red' : 'gray')))) }}-900 dark:text-{{ $list->type === 'reading' ? 'blue' : ($list->type === 'completed' ? 'green' : ($list->type === 'plan_to_read' ? 'yellow' : ($list->type === 'on_hold' ? 'orange' : ($list->type === 'dropped' ? 'red' : 'gray')))) }}-200">
                            {{ ucfirst(str_replace('_', ' ', $list->type)) }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Dialog for List Management -->
    <dialog
        id="list-dialog-{{ $game->id }}"
        class="m-auto rounded-lg bg-white dark:bg-gray-800 p-6 shadow-xl w-full max-w-md dark:text-gray-100 backdrop:backdrop-blur-md"
    >
        <div>
            <x-dialog-header title="Manage Lists for {{ $game->name }}"/>

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

            <x-dialog-footer/>
        </div>
    </dialog>
@else
    <div class="text-sm text-gray-600 dark:text-gray-400">
        <a href="{{ route('login') }}" class="text-blue-600 dark:text-blue-400 hover:underline">Log in</a> to track your reading progress
    </div>
@endauth

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handling click events outside dialog
        document.querySelectorAll('[id^="list-dialog-"]').forEach(dialog => {
            dialog.addEventListener('click', function(e) {
                const dialogDimensions = dialog.getBoundingClientRect();
                if (
                    e.clientX < dialogDimensions.left ||
                    e.clientX > dialogDimensions.right ||
                    e.clientY < dialogDimensions.top ||
                    e.clientY > dialogDimensions.bottom
                ) {
                    dialog.close();
                }
            });

            // Prevent propagation for clicks inside the dialog content
            dialog.querySelector('div').addEventListener('click', function(e) {
                e.stopPropagation();
            });
        });

        // AJAX form handling for default lists
        document.querySelectorAll('.list-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                const form = e.target;
                const gameId = {{ $game->id ?? 'null' }};
                const listType = form.dataset.listType;
                const color = form.dataset.color;
                const label = form.dataset.label;
                const isInList = form.dataset.isInList === 'true';
                const submitButton = form.querySelector('button[type="submit"]');
                const messageContainer = document.getElementById(`ajax-message-${gameId}`);

                // Update UI to loading state
                submitButton.disabled = true;
                submitButton.classList.add('opacity-75');

                const formData = new FormData(form);

                fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    // Show message
                    messageContainer.textContent = data.message;
                    messageContainer.classList.remove('hidden', 'text-red-500', 'text-green-500');
                    messageContainer.classList.add(data.success ? 'text-green-500' : 'text-red-500');

                    // Toggle form state
                    const newIsInList = !isInList;
                    form.dataset.isInList = newIsInList ? 'true' : 'false';

                    // Update button appearance
                    submitButton.className = `w-full flex items-center justify-between px-3 py-2 bg-${color}-${newIsInList ? '500' : '100'} text-${newIsInList ? 'white' : (color+'-800')} hover:bg-${color}-${newIsInList ? '600' : '200'} dark:bg-${color}-${newIsInList ? '700' : '900'} dark:text-${newIsInList ? 'white' : (color+'-200')} dark:hover:bg-${color}-${newIsInList ? '600' : '800'} rounded-md transition-colors`;

                    // Update button text
                    submitButton.innerHTML = `
                        <span>${label}</span>
                        <span class="text-sm font-medium">${newIsInList ? 'Remove' : 'Add'}</span>
                    `;

                    // Update form action and method
                    if (newIsInList) {
                        // Get the entry ID from the response
                        const newEntryId = data.entryId;
                        form.dataset.entryId = newEntryId;
                        form.action = `/list-entries/${newEntryId}`;

                        // Add method override for DELETE
                        let methodInput = form.querySelector('input[name="_method"]');
                        if (!methodInput) {
                            methodInput = document.createElement('input');
                            methodInput.type = 'hidden';
                            methodInput.name = '_method';
                            form.appendChild(methodInput);
                        }
                        methodInput.value = 'DELETE';

                        // Remove list_type input if it exists
                        const listTypeInput = form.querySelector('input[name="list_type"]');
                        if (listTypeInput) {
                            form.removeChild(listTypeInput);
                        }
                    } else {
                        form.action = `/games/${gameId}/add-to-list`;

                        // Remove method override if it exists
                        const methodInput = form.querySelector('input[name="_method"]');
                        if (methodInput) {
                            form.removeChild(methodInput);
                        }

                        // Add list_type input if it doesn't exist
                        let listTypeInput = form.querySelector('input[name="list_type"]');
                        if (!listTypeInput) {
                            listTypeInput = document.createElement('input');
                            listTypeInput.type = 'hidden';
                            listTypeInput.name = 'list_type';
                            listTypeInput.value = listType;
                            form.appendChild(listTypeInput);
                        }
                    }

                    // Re-enable button
                    submitButton.disabled = false;
                    submitButton.classList.remove('opacity-75');

                    // Remove message after a delay
                    setTimeout(() => {
                        messageContainer.classList.add('hidden');
                    }, 3000);
                })
                .catch(error => {
                    console.error('Error:', error);
                    messageContainer.textContent = 'An error occurred. Please try again.';
                    messageContainer.classList.remove('hidden', 'text-green-500');
                    messageContainer.classList.add('text-red-500');

                    // Re-enable button
                    submitButton.disabled = false;
                    submitButton.classList.remove('opacity-75');
                });
            });
        });

        // AJAX form handling for custom lists
        document.querySelectorAll('form[data-custom-list-form]').forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                const submitButton = form.querySelector('button[type="submit"]');
                submitButton.disabled = true;

                try {
                    const formData = new FormData(form);
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: formData
                    });

                    const data = await response.json();

                    if (response.ok) {
                        // Toggle only this button's state
                        if (submitButton.classList.contains('bg-blue-600')) {
                            submitButton.classList.remove('bg-blue-600', 'text-white', 'hover:bg-blue-700');
                            submitButton.classList.add('bg-gray-100', 'dark:bg-gray-700', 'hover:bg-gray-200', 'dark:hover:bg-gray-600');
                            // Remove the "Remove" text
                            const removeSpan = submitButton.querySelector('.text-sm.font-medium');
                            if (removeSpan) {
                                removeSpan.remove();
                            }
                        } else {
                            submitButton.classList.remove('bg-gray-100', 'dark:bg-gray-700', 'hover:bg-gray-200', 'dark:hover:bg-gray-600');
                            submitButton.classList.add('bg-blue-600', 'text-white', 'hover:bg-blue-700');
                            // Add the "Remove" text if it doesn't exist
                            if (!submitButton.querySelector('.text-sm.font-medium')) {
                                const removeSpan = document.createElement('span');
                                removeSpan.className = 'text-sm font-medium';
                                removeSpan.textContent = 'Remove';
                                submitButton.appendChild(removeSpan);
                            }
                        }

                        showMessage(document.getElementById(`ajax-message-${form.dataset.gameId}`), data.message, true);
                    } else {
                        throw new Error(data.message || 'Failed to update list');
                    }
                } catch (error) {
                    showMessage(document.getElementById(`ajax-message-${form.dataset.gameId}`), error.message, false);
                } finally {
                    submitButton.disabled = false;
                }
            });
        });

        // Function to update message
        let messageTimeouts = {};
        function showMessage(messageDiv, text, isSuccess) {
            // Clear any existing timeout for this message div
            if (messageTimeouts[messageDiv.id]) {
                clearTimeout(messageTimeouts[messageDiv.id]);
            }

            messageDiv.textContent = text;
            messageDiv.className = `h-6 text-sm text-center ${isSuccess ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`;

            // Set new timeout and store its ID
            messageTimeouts[messageDiv.id] = setTimeout(() => {
                messageDiv.textContent = '';
                messageDiv.className = 'h-6 text-sm text-center';
                delete messageTimeouts[messageDiv.id];
            }, 5000);
        }

        // Handle default list forms (reading, completed, etc.)
        document.querySelectorAll('form[data-default-list-form]').forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                const submitButton = form.querySelector('button[type="submit"]');
                const removeSpan = submitButton.querySelector('.text-sm.font-medium');
                submitButton.disabled = true;

                try {
                    const formData = new FormData(form);
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: formData
                    });

                    const data = await response.json();

                    if (response.ok) {
                        if (data.message.includes('removed')) {
                            // If the game was removed, update this button to inactive state
                            submitButton.classList.remove('bg-blue-600', 'text-white', 'hover:bg-blue-700');
                            submitButton.classList.add('bg-gray-100', 'dark:bg-gray-700', 'hover:bg-gray-200', 'dark:hover:bg-gray-600');
                            // Remove the "Remove" text
                            if (removeSpan) {
                                removeSpan.remove();
                            }
                        } else {
                            // If the game was added or moved, first update all default list buttons to inactive state
                            document.querySelectorAll(`[data-default-list][data-game-id="${form.dataset.gameId}"]`).forEach(btn => {
                                btn.classList.remove('bg-blue-600', 'text-white', 'hover:bg-blue-700');
                                btn.classList.add('bg-gray-100', 'dark:bg-gray-700', 'hover:bg-gray-200', 'dark:hover:bg-gray-600');
                                // Remove any "Remove" text
                                const btnRemoveSpan = btn.querySelector('.text-sm.font-medium');
                                if (btnRemoveSpan) {
                                    btnRemoveSpan.remove();
                                }
                            });

                            // Then update this button to active state
                            submitButton.classList.remove('bg-gray-100', 'dark:bg-gray-700', 'hover:bg-gray-200', 'dark:hover:bg-gray-600');
                            submitButton.classList.add('bg-blue-600', 'text-white', 'hover:bg-blue-700');
                            // Add the "Remove" text
                            if (!removeSpan) {
                                submitButton.insertAdjacentHTML('beforeend', '<span class="text-sm font-medium">Remove</span>');
                            }
                        }

                        // Update the list tags on the game detail page
                        const listTagsContainer = document.querySelector(`[data-list-tags="${form.dataset.gameId}"]`);
                        if (listTagsContainer) {
                            const listType = formData.get('list_type');
                            const bgColor = listType === 'reading' ? 'blue' : (listType === 'completed' ? 'green' : (listType === 'plan_to_read' ? 'yellow' : (listType === 'on_hold' ? 'orange' : (listType === 'dropped' ? 'red' : 'gray'))));
                            const listTypeFormatted = listType.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');

                            if (data.message.includes('removed')) {
                                // Remove all tags (both list type and public)
                                listTagsContainer.innerHTML = '';
                            } else {
                                // Remove any existing tags first
                                listTagsContainer.innerHTML = '';

                                // Add the new list type tag
                                const tag = document.createElement('span');
                                tag.setAttribute('data-list-type', listType);
                                tag.className = `px-2 py-1 text-xs font-semibold rounded-full bg-${bgColor}-100 text-${bgColor}-800 dark:bg-${bgColor}-900 dark:text-${bgColor}-200`;
                                tag.textContent = listTypeFormatted;
                                listTagsContainer.appendChild(tag);

                                // Add public tag if the list is public
                                if (data.is_public) {
                                    const publicTag = document.createElement('span');
                                    publicTag.className = 'px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200';
                                    publicTag.textContent = 'Public';
                                    listTagsContainer.appendChild(publicTag);
                                }
                            }
                        }

                        showMessage(document.getElementById(`ajax-message-${form.dataset.gameId}`), data.message, true);
                    } else {
                        throw new Error(data.message || 'Failed to update list');
                    }
                } catch (error) {
                    showMessage(document.getElementById(`ajax-message-${form.dataset.gameId}`), error.message, false);
                } finally {
                    submitButton.disabled = false;
                }
            });
        });

        // Handle quick list creation
        document.querySelectorAll('.quick-list-form').forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                const submitButton = form.querySelector('button[type="submit"]');
                const originalText = submitButton.textContent;
                submitButton.textContent = 'Creating...';
                submitButton.disabled = true;

                try {
                    const formData = new FormData(form);
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: formData
                    });

                    const data = await response.json();

                    if (response.ok) {
                        // Clear the form
                        form.querySelector('input[name="name"]').value = '';

                        // Add the new list to the custom lists section
                        const customListsContainer = document.getElementById(`custom-lists-${formData.get('game_id')}`);
                        if (customListsContainer) {
                            const newListForm = document.createElement('form');
                            newListForm.action = `/list-entries/add-to-custom/${data.list.id}`;
                            newListForm.method = 'POST';
                            newListForm.setAttribute('data-custom-list-form', '');
                            newListForm.setAttribute('data-game-id', formData.get('game_id'));

                            // Add CSRF token - get it from the original form
                            const csrfToken = form.querySelector('input[name="_token"]').value;
                            const csrfInput = document.createElement('input');
                            csrfInput.type = 'hidden';
                            csrfInput.name = '_token';
                            csrfInput.value = csrfToken;
                            newListForm.appendChild(csrfInput);

                            // Add game_id input
                            const gameIdInput = document.createElement('input');
                            gameIdInput.type = 'hidden';
                            gameIdInput.name = 'game_id';
                            gameIdInput.value = formData.get('game_id');
                            newListForm.appendChild(gameIdInput);

                            // Add the button - start in active state since we're adding to it
                            const button = document.createElement('button');
                            button.type = 'submit';
                            button.setAttribute('data-game-id', formData.get('game_id'));
                            button.className = 'w-full text-left px-4 py-2 text-sm bg-blue-600 text-white hover:bg-blue-700 flex items-center justify-between';
                            const buttonText = document.createElement('span');
                            buttonText.textContent = data.list.name;
                            button.appendChild(buttonText);
                            const removeText = document.createElement('span');
                            removeText.className = 'text-sm font-medium';
                            removeText.textContent = 'Remove';
                            button.appendChild(removeText);
                            newListForm.appendChild(button);

                            // Add the form to the container
                            customListsContainer.appendChild(newListForm);

                            // Add event listener to the new form
                            newListForm.addEventListener('submit', async function(e) {
                                e.preventDefault();
                                const submitButton = newListForm.querySelector('button[type="submit"]');
                                const originalText = submitButton.textContent;
                                submitButton.textContent = 'Moving...';
                                submitButton.disabled = true;

                                try {
                                    const formData = new FormData(newListForm);
                                    const response = await fetch(newListForm.action, {
                                        method: 'POST',
                                        headers: {
                                            'X-Requested-With': 'XMLHttpRequest',
                                            'Accept': 'application/json'
                                        },
                                        body: formData
                                    });

                                    const data = await response.json();

                                    if (response.ok) {
                                        // Toggle only this button's state
                                        if (submitButton.classList.contains('bg-blue-600')) {
                                            submitButton.classList.remove('bg-blue-600', 'text-white', 'hover:bg-blue-700');
                                            submitButton.classList.add('bg-gray-100', 'dark:bg-gray-700', 'hover:bg-gray-200', 'dark:hover:bg-gray-600');
                                        } else {
                                            submitButton.classList.remove('bg-gray-100', 'dark:bg-gray-700', 'hover:bg-gray-200', 'dark:hover:bg-gray-600');
                                            submitButton.classList.add('bg-blue-600', 'text-white', 'hover:bg-blue-700');
                                        }

                                        showMessage(document.getElementById(`ajax-message-${newListForm.dataset.gameId}`), data.message, true);
                                    } else {
                                        throw new Error(data.message || 'Failed to update list');
                                    }
                                } catch (error) {
                                    showMessage(document.getElementById(`ajax-message-${newListForm.dataset.gameId}`), error.message, false);
                                } finally {
                                    submitButton.textContent = originalText;
                                    submitButton.disabled = false;
                                }
                            });
                        }

                        showMessage(document.getElementById(`ajax-message-${form.querySelector('input[name="game_id"]').value}`), data.message, true);
                    } else {
                        throw new Error(data.message || 'Failed to create list');
                    }
                } catch (error) {
                    showMessage(document.getElementById(`ajax-message-${form.querySelector('input[name="game_id"]').value}`), error.message, false);
                } finally {
                    submitButton.textContent = originalText;
                    submitButton.disabled = false;
                }
            });
        });

        document.querySelectorAll('form[data-list-form]').forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                const submitButton = form.querySelector('button[type="submit"]');
                const originalText = submitButton.textContent;
                submitButton.textContent = 'Moving...';
                submitButton.disabled = true;

                try {
                    const response = await fetch(form.action, {
                        method: form.method,
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(Object.fromEntries(new FormData(form)))
                    });

                    const data = await response.json();

                    if (response.ok) {
                        // Update all list buttons to show inactive state
                        document.querySelectorAll(`[data-game-id="${form.dataset.gameId}"]`).forEach(btn => {
                            btn.classList.remove('bg-blue-600', 'text-white', 'hover:bg-blue-700');
                            btn.classList.add('bg-gray-100', 'dark:bg-gray-700', 'hover:bg-gray-200', 'dark:hover:bg-gray-600');
                        });

                        // Update clicked button to show active state
                        const listButton = form.closest('[data-game-id]');
                        if (listButton) {
                            listButton.classList.remove('bg-gray-100', 'dark:bg-gray-700', 'hover:bg-gray-200', 'dark:hover:bg-gray-600');
                            listButton.classList.add('bg-blue-600', 'text-white', 'hover:bg-blue-700');
                        }

                        // Show success message
                        const messageDiv = document.getElementById(`ajax-message-${form.dataset.gameId}`);
                        messageDiv.textContent = data.message;
                        messageDiv.className = 'mt-4 text-sm text-green-600 dark:text-green-400';
                        messageDiv.classList.remove('hidden');

                        // Hide message after 3 seconds
                        setTimeout(() => {
                            messageDiv.classList.add('hidden');
                        }, 3000);
                    } else {
                        throw new Error(data.message || 'Failed to update list');
                    }
                } catch (error) {
                    const messageDiv = document.getElementById(`ajax-message-${form.dataset.gameId}`);
                    messageDiv.textContent = error.message;
                    messageDiv.className = 'mt-4 text-sm text-red-600 dark:text-red-400';
                    messageDiv.classList.remove('hidden');
                } finally {
                    submitButton.textContent = originalText;
                    submitButton.disabled = false;
                }
            });
        });
    });
</script>
