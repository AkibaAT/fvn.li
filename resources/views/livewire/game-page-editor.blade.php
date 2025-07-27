<div class="game-page-editor" data-game-id="{{ $game->id }}">
    {{-- Success/Error Messages --}}
    @if ($message)
        <div class="mb-4 p-4 rounded-lg border {{ $messageType === 'success' ? 'bg-green-50 border-green-200 text-green-800 dark:bg-green-900/20 dark:border-green-800 dark:text-green-200' : 'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/20 dark:border-red-800 dark:text-red-200' }}">
            <div class="flex items-center justify-between">
                <span>{{ $message }}</span>
                <button wire:click="clearMessage" class="text-sm opacity-70 hover:opacity-100 ml-2 flex-shrink-0">&times;</button>
            </div>
        </div>
    @endif

    {{-- Edit Mode Toggle & Status --}}
    <div class="flex items-center justify-between mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="flex items-center space-x-4">
            @if ($this->isCustomPage)
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path>
                    </svg>
                    Custom Page Active
                </span>
            @else
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"></path>
                    </svg>
                    Auto-Sync from itch.io
                </span>
            @endif

            @if ($this->lastUpdated)
                <span class="text-sm text-gray-600 dark:text-gray-400">
                    Last updated {{ $this->lastUpdated }}
                    @if ($this->updatedBy)
                        by {{ $this->updatedBy->name }}
                    @endif
                </span>
            @endif
        </div>

        @if ($this->canEdit())
            <div class="flex items-center space-x-2">
                @if (!$editMode)
                    @if ($this->isCustomPage)
                        <button onclick="if(confirm('Disable custom editing and re-enable auto-sync?')) { @this.disableCustomPage(); }"
                                wire:loading.attr="disabled"
                                wire:target="disableCustomPage"
                                class="px-4 py-2 text-sm bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors disabled:opacity-50">
                            <span wire:loading.remove wire:target="disableCustomPage">Disable Custom Page</span>
                            <span wire:loading wire:target="disableCustomPage" class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Disabling...
                            </span>
                        </button>
                    @else
                        <button wire:click="enableCustomPage"
                                wire:loading.attr="disabled"
                                wire:target="enableCustomPage"
                                class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50">
                            <span wire:loading.remove wire:target="enableCustomPage">Enable Custom Page</span>
                            <span wire:loading wire:target="enableCustomPage" class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Enabling...
                            </span>
                        </button>
                    @endif

                    <button wire:click="toggleEditMode"
                            wire:loading.attr="disabled"
                            wire:target="toggleEditMode"
                            class="px-4 py-2 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50">
                        <span wire:loading.remove wire:target="toggleEditMode" class="flex items-center">
                            <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path>
                            </svg>
                            Edit Page
                        </span>
                        <span wire:loading wire:target="toggleEditMode" class="flex items-center">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Loading Editor...
                        </span>
                    </button>
                @else
                    <button wire:click="save"
                            wire:loading.attr="disabled"
                            wire:target="save"
                            class="px-4 py-2 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50">
                        <span wire:loading.remove wire:target="save">Save Changes</span>
                        <span wire:loading wire:target="save" class="flex items-center">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Saving...
                        </span>
                    </button>
                    <button type="button"
                            onclick="setTimeout(() => { this.disabled = true; @this.set('editMode', false); }, 50);"
                            class="px-4 py-2 text-sm bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors disabled:opacity-50">
                        <span wire:loading.remove wire:target="cancel">Cancel</span>
                        <span wire:loading wire:target="cancel">Canceling...</span>
                    </button>
                @endif
            </div>
        @endif
    </div>

    {{-- Content Area with Smooth Transitions --}}
    <div wire:loading.class="opacity-50" wire:target="toggleEditMode,enableCustomPage,disableCustomPage"
         class="transition-opacity duration-200">
        @if ($editMode)
            {{-- Tabbed Editor Interface --}}
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm">
                {{-- Tab Navigation --}}
                <div class="border-b border-gray-200 dark:border-gray-700">
                    <nav class="flex space-x-8 px-6 pt-4" aria-label="Tabs">
                        <button wire:click="switchTab('edit')"
                                class="border-b-2 py-2 px-1 text-sm font-medium transition-colors
                                       {{ $activeTab === 'edit'
                                          ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                          : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}">
                            <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path>
                            </svg>
                            Edit
                        </button>
                        <button wire:click="switchTab('preview')"
                                class="border-b-2 py-2 px-1 text-sm font-medium transition-colors
                                       {{ $activeTab === 'preview'
                                          ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                          : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}">
                            <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path fill-rule="evenodd" d="M1.323 11.447C2.811 6.976 7.028 3.75 12.001 3.75c4.97 0 9.185 3.223 10.675 7.69.12.362.12.752 0 1.113-1.487 4.471-5.705 7.697-10.677 7.697-4.97 0-9.186-3.223-10.675-7.69a1.762 1.762 0 010-1.113zM17.25 12a5.25 5.25 0 11-10.5 0 5.25 5.25 0 0110.5 0z" clip-rule="evenodd"></path>
                            </svg>
                            Preview
                        </button>
                    </nav>
                </div>

                {{-- Tab Content --}}
                <div class="p-3">
                {{-- Edit Tab --}}
                @if ($activeTab === 'edit')
                    {{-- Description Editor --}}
                    <div class="mb-3">
                        <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Game Description
                        </label>
                        <div wire:ignore class="min-h-[600px]">
                            <textarea
                                id="description"
                                rows="20"
                                class="w-full p-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-y"
                                style="min-height: 600px;"
                                placeholder="Enter your game description here..."
                            >{{ $description }}</textarea>
                        </div>
                        @error('description')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- File Upload for Images in Description --}}
                    <div class="mb-6" wire:ignore.self>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Upload Images for Description
                        </label>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 dark:border-gray-600 border-dashed rounded-lg hover:border-gray-400 dark:hover:border-gray-500 transition-colors">
                            <div class="space-y-1 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div class="flex text-sm text-gray-600 dark:text-gray-400">
                                    <label for="file-upload" class="relative cursor-pointer bg-white dark:bg-gray-800 rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                        <span>Upload multiple files</span>
                                        <input id="file-upload" wire:model="uploadedFiles" type="file" multiple accept="image/*" class="sr-only">
                                    </label>
                                    <p class="pl-1">or drag and drop multiple files</p>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">PNG, JPG, GIF, WebP up to 10MB each • Select multiple files at once</p>
                                <p class="text-xs text-gray-600 dark:text-gray-400 mt-2">
                                    These images can be inserted into your description using the TinyMCE image button
                                </p>
                            </div>
                        </div>
                        @if ($uploadedFiles)
                            <div class="mt-4">
                                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    {{ count($uploadedFiles) }} file{{ count($uploadedFiles) !== 1 ? 's' : '' }} selected:
                                </h4>
                                <ul class="space-y-1">
                                    @foreach ($uploadedFiles as $file)
                                        <li class="text-sm text-gray-600 dark:text-gray-400 flex items-center">
                                            <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                            </svg>
                                            {{ $file->getClientOriginalName() }}
                                            <span class="ml-auto text-xs text-gray-500">
                                                {{ number_format($file->getSize() / 1024 / 1024, 1) }}MB
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <div class="mt-3 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                            <p class="text-sm text-blue-800 dark:text-blue-200">
                                <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                </svg>
                                <strong>Note:</strong> This section is for uploading images to include in your description text. Screenshots are managed separately in the main gallery section below.
                            </p>
                        </div>
                    </div>
                @endif

                {{-- Preview Tab --}}
                @if ($activeTab === 'preview')
                    <div id="preview-content" class="prose dark:prose-invert max-w-none min-h-96">
                        {!! $this->descriptionPreview !!}
                    </div>
                @endif
            </div>
        </div>
    @else
        {{-- Display Mode --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm">
            <div class="p-6">
                @if ($description)
                    <div class="prose dark:prose-invert max-w-none game_description">
                        {!! $description !!}
                    </div>
                @else
                    <p class="text-gray-500 dark:text-gray-400 italic">No description available.</p>
                @endif

            </div>
        </div>
        @endif
    </div>

    {{-- Image Picker Dialog (only in edit mode) --}}
    @if ($editMode)
        <x-image-picker-dialog :game-id="$game->id" />
    @endif
</div>

@script
<script>
    // Auto-clear messages after 5 seconds
    $wire.on('clearMessage', () => {
        setTimeout(() => {
            $wire.clearMessage();
        }, 5000);
    });

    // Initialize TinyMCE when requested
    $wire.on('initializeTinyMCE', () => {
        setTimeout(() => {
            if (typeof window.initTinyMCE === 'function') {
                window.initTinyMCE();
            }
        }, 100);
    });

    // Simple image modal function (you can enhance this)
    window.openImageModal = function(imageSrc) {
        // You can implement a more sophisticated modal here
        window.open(imageSrc, '_blank');
    };

    // Image picker dialog function
    window.openImagePickerDialog = function() {
        if (window._imagePickerDialog) {
            // Trigger Alpine.js to load images by dispatching a custom event
            window._imagePickerDialog.dispatchEvent(new CustomEvent('load-images'));
            window._imagePickerDialog.showModal();
        } else {
            console.error('Image picker dialog not properly initialized');
        }
    };

    // Debounced preview update function
    let previewUpdateTimeout;
    window.updatePreview = function(content) {
        clearTimeout(previewUpdateTimeout);
        previewUpdateTimeout = setTimeout(() => {
            const previewElement = document.getElementById('preview-content');
            if (previewElement) {
                previewElement.innerHTML = content;

                // Apply dark mode classes to preview content if needed
                const isDark = document.documentElement.classList.contains('dark');
                if (isDark) {
                    previewElement.classList.add('dark:prose-invert');
                } else {
                    previewElement.classList.remove('dark:prose-invert');
                }
            }
        }, 300); // 300ms debounce
    }

    // Track TinyMCE state to prevent unnecessary operations
    let tinyMCEInitialized = false;

    // Initialize TinyMCE when entering edit mode
    Livewire.hook('morph.updated', ({ el, component }) => {
        const descElement = document.getElementById('description');

        // Only initialize if:
        // 1. Description element exists
        // 2. Element is visible
        // 3. TinyMCE is not already initialized
        // 4. We haven't already tracked it as initialized
        if (descElement &&
            descElement.offsetParent !== null &&
            !tinyMCEInitialized &&
            !window.tinymce?.get('description')) {

            console.log('Description element found and needs TinyMCE initialization');
            tinyMCEInitialized = true;

            // Small delay to ensure DOM is stable
            setTimeout(() => {
                if (typeof window.initTinyMCE === 'function') {
                    console.log('Calling initTinyMCE');
                    window.initTinyMCE();
                } else {
                    console.error('initTinyMCE function not available');
                    tinyMCEInitialized = false; // Reset on error
                }
            }, 100);
        }
    });

    // Only destroy TinyMCE when actually leaving edit mode (not on every morph)
    $wire.on('exitEditMode', () => {
        if (tinyMCEInitialized && typeof window.destroyTinyMCE === 'function') {
            window.destroyTinyMCE();
            tinyMCEInitialized = false;
        }
    });

    // Sync TinyMCE content before save
    $wire.on('save', () => {
        if (window.tinymce && window.tinymce.get('description')) {
            const content = window.tinymce.get('description').getContent();
            $wire.set('description', content);
        }
    });

    // Dialog event listeners (standard pattern)
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

    // Global function for image picker dialog
    window.imagePickerData = function(gameId) {
        return {
            images: [],
            loading: false,
            error: null,

            async loadImages() {
                this.loading = true;
                this.error = null;

                try {
                    const response = await fetch(`/api/editor-images?game_id=${gameId}`, {
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                        }
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const data = await response.json();
                    this.images = data.images || [];
                } catch (error) {
                    console.error('Failed to load images:', error);
                    this.error = error.message;
                    this.images = [];
                } finally {
                    this.loading = false;
                }
            },

            selectImage(imageUrl) {
                // Dispatch custom event with selected image URL
                window.dispatchEvent(new CustomEvent('image-selected', {
                    detail: { imageUrl }
                }));
                // Close the dialog by finding it in the DOM
                document.getElementById('image-picker-dialog').close();
            },

            async deleteImage(imagePath) {
                if (!confirm('Are you sure you want to delete this image? This action cannot be undone.')) {
                    return;
                }

                try {
                    const encodedPath = encodeURIComponent(imagePath);
                    const response = await fetch(`/api/editor-images/${encodedPath}?game_id=${gameId}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                            'Content-Type': 'application/json',
                        }
                    });

                    if (!response.ok) {
                        const errorData = await response.json();
                        throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
                    }

                    // Reload images after successful deletion
                    await this.loadImages();

                } catch (error) {
                    console.error('Delete error:', error);
                    alert(`Failed to delete image: ${error.message}`);
                }
            },

            formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }
        };
    };
</script>
@endscript