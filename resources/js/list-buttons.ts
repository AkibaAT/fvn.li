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
        const dialogContent = dialog.querySelector('div');
        if (dialogContent) {
            dialogContent.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }
    });

    // AJAX form handling for custom lists
    document.querySelectorAll('form[data-custom-list-form]').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const submitButton = form.querySelector('button[type="submit"]') as HTMLButtonElement;
            if (!submitButton) return;
            
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

                    const gameId = form.dataset.gameId;
                    if (gameId) {
                        const messageElement = document.getElementById(`ajax-message-${gameId}`);
                        if (messageElement) {
                            showMessage(messageElement, data.message, true);
                        }
                    }
                } else {
                    throw new Error(data.message || 'Failed to update list');
                }
            } catch (error) {
                const gameId = form.dataset.gameId;
                if (gameId) {
                    const messageElement = document.getElementById(`ajax-message-${gameId}`);
                    if (messageElement && error instanceof Error) {
                        showMessage(messageElement, error.message, false);
                    }
                }
            } finally {
                submitButton.disabled = false;
            }
        });
    });

    // Function to update message
    const messageTimeouts: Record<string, number> = {};
    function showMessage(messageDiv: HTMLElement, text: string, isSuccess: boolean) {
        // Clear any existing timeout for this message div
        if (messageTimeouts[messageDiv.id]) {
            clearTimeout(messageTimeouts[messageDiv.id]);
        }

        messageDiv.textContent = text;
        messageDiv.className = `h-6 text-sm text-center ${isSuccess ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`;

        // Set new timeout and store its ID
        messageTimeouts[messageDiv.id] = window.setTimeout(() => {
            messageDiv.textContent = '';
            messageDiv.className = 'h-6 text-sm text-center';
            delete messageTimeouts[messageDiv.id];
        }, 5000);
    }

    // Handle default list forms (reading, completed, etc.)
    document.querySelectorAll('form[data-default-list-form]').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const submitButton = form.querySelector('button[type="submit"]') as HTMLButtonElement;
            if (!submitButton) return;
            
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
                    const gameId = form.dataset.gameId;
                    if (!gameId) return;
                    
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
                        document.querySelectorAll(`[data-default-list][data-game-id="${gameId}"]`).forEach(btn => {
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
                    const listTagsContainer = document.querySelector(`[data-list-tags="${gameId}"]`);
                    if (listTagsContainer) {
                        const listType = formData.get('list_type');
                        if (typeof listType === 'string') {
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
                    }

                    // Update the My Lists badge color based on the new list type
                    const listType = formData.get('list_type');
                    if (typeof listType === 'string') {
                        updateMyListsBadgeColor(gameId, listType);
                        updateUserListsContent(gameId, listType, data.message.includes('removed'), data.is_public);
                    }

                    const messageElement = document.getElementById(`ajax-message-${gameId}`);
                    if (messageElement) {
                        showMessage(messageElement, data.message, true);
                    }
                } else {
                    throw new Error(data.message || 'Failed to update list');
                }
            } catch (error) {
                const gameId = form.dataset.gameId;
                if (gameId) {
                    const messageElement = document.getElementById(`ajax-message-${gameId}`);
                    if (messageElement && error instanceof Error) {
                        showMessage(messageElement, error.message, false);
                    }
                }
            } finally {
                submitButton.disabled = false;
            }
        });
    });

    // Function to update My Lists badge color
    function updateMyListsBadgeColor(gameId: string, listType: string) {
        const myListsButton = document.querySelector(`button[onclick="toggleUserLists('${gameId}')"]`);
        if (!myListsButton) return;

        const badge = myListsButton.querySelector('span.rounded-full');
        if (!badge) return;

        // Remove existing color classes
        badge.classList.remove(
            'bg-blue-100', 'text-blue-800', 'dark:bg-blue-900', 'dark:text-blue-200',
            'bg-green-100', 'text-green-800', 'dark:bg-green-900', 'dark:text-green-200',
            'bg-yellow-100', 'text-yellow-800', 'dark:bg-yellow-900', 'dark:text-yellow-200',
            'bg-orange-100', 'text-orange-800', 'dark:bg-orange-900', 'dark:text-orange-200',
            'bg-red-100', 'text-red-800', 'dark:bg-red-900', 'dark:text-red-200'
        );

        // Add new color classes based on list type
        const bgColor = listType === 'reading' ? 'blue' : (listType === 'completed' ? 'green' : (listType === 'plan_to_read' ? 'yellow' : (listType === 'on_hold' ? 'orange' : (listType === 'dropped' ? 'red' : 'gray'))));
        badge.classList.add(`bg-${bgColor}-100`, `text-${bgColor}-800`, `dark:bg-${bgColor}-900`, `dark:text-${bgColor}-200`);
    }

    // Function to update user lists content
    function updateUserListsContent(gameId: string, listType: string, isRemoved: boolean, isPublic: boolean) {
        const userListsContainer = document.getElementById(`user-lists-${gameId}`);
        if (!userListsContainer) return;

        // Find all list items in the user lists container
        const listItems = userListsContainer.querySelectorAll('.flex.flex-col.p-4');
        
        // Update the active state of each list item
        listItems.forEach(item => {
            const listTypeElement = item.querySelector('.text-xs.text-gray-500');
            const listTypeText = listTypeElement?.textContent?.trim().toLowerCase();
            const isThisList = listTypeText && listTypeText.includes(listType.replace('_', ' '));
            
            if (isThisList) {
                if (isRemoved) {
                    // Remove active state
                    item.classList.remove('border-blue-500');
                    item.classList.add('border-gray-100', 'dark:border-gray-700');
                } else {
                    // Add active state
                    item.classList.remove('border-gray-100', 'dark:border-gray-700');
                    item.classList.add('border-blue-500');
                }
            }
        });
    }

    // Handle quick list creation
    document.querySelectorAll('.quick-list-form').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const submitButton = form.querySelector('button[type="submit"]') as HTMLButtonElement;
            if (!submitButton) return;
            
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
                    const nameInput = form.querySelector('input[name="name"]') as HTMLInputElement;
                    if (nameInput) {
                        nameInput.value = '';
                    }

                    // Add the new list to the custom lists section
                    const gameIdInput = form.querySelector('input[name="game_id"]') as HTMLInputElement;
                    if (gameIdInput) {
                        const gameId = gameIdInput.value;
                        const customListsContainer = document.getElementById(`custom-lists-${gameId}`);
                        if (customListsContainer) {
                            const newListForm = document.createElement('form');
                            newListForm.action = `/list-entries/add-to-custom/${data.list.id}`;
                            newListForm.method = 'POST';
                            newListForm.setAttribute('data-custom-list-form', '');
                            newListForm.setAttribute('data-game-id', gameId);

                            // Add CSRF token - get it from the original form
                            const csrfTokenInput = form.querySelector('input[name="_token"]') as HTMLInputElement;
                            if (csrfTokenInput) {
                                const csrfToken = csrfTokenInput.value;
                                const csrfInput = document.createElement('input');
                                csrfInput.type = 'hidden';
                                csrfInput.name = '_token';
                                csrfInput.value = csrfToken;
                                newListForm.appendChild(csrfInput);
                            }

                            // Add game_id input
                            const gameIdInput = document.createElement('input');
                            gameIdInput.type = 'hidden';
                            gameIdInput.name = 'game_id';
                            gameIdInput.value = gameId;
                            newListForm.appendChild(gameIdInput);

                            // Add the button - start in active state since we're adding to it
                            const button = document.createElement('button');
                            button.type = 'submit';
                            button.setAttribute('data-game-id', gameId);
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
                                const submitButton = newListForm.querySelector('button[type="submit"]') as HTMLButtonElement;
                                if (!submitButton) return;
                                
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

                                        const gameId = newListForm.dataset.gameId;
                                        if (gameId) {
                                            const messageElement = document.getElementById(`ajax-message-${gameId}`);
                                            if (messageElement) {
                                                showMessage(messageElement, data.message, true);
                                            }
                                        }
                                    } else {
                                        throw new Error(data.message || 'Failed to update list');
                                    }
                                } catch (error) {
                                    const gameId = newListForm.dataset.gameId;
                                    if (gameId) {
                                        const messageElement = document.getElementById(`ajax-message-${gameId}`);
                                        if (messageElement && error instanceof Error) {
                                            showMessage(messageElement, error.message, false);
                                        }
                                    }
                                } finally {
                                    submitButton.textContent = originalText;
                                    submitButton.disabled = false;
                                }
                            });
                        }
                        
                        const messageElement = document.getElementById(`ajax-message-${gameId}`);
                        if (messageElement) {
                            showMessage(messageElement, data.message, true);
                        }
                    }
                } else {
                    throw new Error(data.message || 'Failed to create list');
                }
            } catch (error) {
                const gameIdInput = form.querySelector('input[name="game_id"]') as HTMLInputElement;
                if (gameIdInput) {
                    const gameId = gameIdInput.value;
                    const messageElement = document.getElementById(`ajax-message-${gameId}`);
                    if (messageElement && error instanceof Error) {
                        showMessage(messageElement, error.message, false);
                    }
                }
            } finally {
                submitButton.textContent = originalText || 'Create';
                submitButton.disabled = false;
            }
        });
    });

    document.querySelectorAll('form[data-list-form]').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const submitButton = form.querySelector('button[type="submit"]') as HTMLButtonElement;
            if (!submitButton) return;
            
            const originalText = submitButton.textContent;
            submitButton.textContent = 'Moving...';
            submitButton.disabled = true;

            try {
                const formData = new FormData(form);
                const formDataObject: Record<string, string> = {};
                formData.forEach((value, key) => {
                    if (typeof value === 'string') {
                        formDataObject[key] = value;
                    }
                });
                
                const response = await fetch(form.action, {
                    method: form.method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(formDataObject)
                });

                const data = await response.json();

                if (response.ok) {
                    const gameId = form.dataset.gameId;
                    if (!gameId) return;
                    
                    // Update all list buttons to show inactive state
                    document.querySelectorAll(`[data-game-id="${gameId}"]`).forEach(btn => {
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
                    const messageDiv = document.getElementById(`ajax-message-${gameId}`);
                    if (messageDiv) {
                        messageDiv.textContent = data.message;
                        messageDiv.className = 'mt-4 text-sm text-green-600 dark:text-green-400';
                        messageDiv.classList.remove('hidden');

                        // Hide message after 3 seconds
                        setTimeout(() => {
                            messageDiv.classList.add('hidden');
                        }, 3000);
                    }
                } else {
                    throw new Error(data.message || 'Failed to update list');
                }
            } catch (error) {
                const gameId = form.dataset.gameId;
                if (gameId) {
                    const messageDiv = document.getElementById(`ajax-message-${gameId}`);
                    if (messageDiv && error instanceof Error) {
                        messageDiv.textContent = error.message;
                        messageDiv.className = 'mt-4 text-sm text-red-600 dark:text-red-400';
                        messageDiv.classList.remove('hidden');
                    }
                }
            } finally {
                submitButton.textContent = originalText || '';
                submitButton.disabled = false;
            }
        });
    });
});

// These functions need to be globally available
(window as any).toggleUserLists = function(gameId: string) {
    const container = document.getElementById(`user-lists-${gameId}`);
    const chevron = document.getElementById(`user-lists-chevron-${gameId}`);

    if (container && chevron) {
        if (container.classList.contains('hidden')) {
            container.classList.remove('hidden');
            chevron.style.transform = 'rotate(180deg)';
        } else {
            container.classList.add('hidden');
            chevron.style.transform = 'rotate(0deg)';
        }
    }
};

(window as any).togglePublicLists = function(gameId: string) {
    const container = document.getElementById(`public-lists-${gameId}`);
    const chevron = document.getElementById(`public-lists-chevron-${gameId}`);

    if (container && chevron) {
        if (container.classList.contains('hidden')) {
            container.classList.remove('hidden');
            chevron.style.transform = 'rotate(180deg)';
        } else {
            container.classList.add('hidden');
            chevron.style.transform = 'rotate(0deg)';
        }
    }
};
