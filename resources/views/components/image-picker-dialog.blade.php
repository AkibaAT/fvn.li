@props(['gameId'])

<dialog
    id="image-picker-dialog"
    class="m-auto rounded-lg bg-white dark:bg-gray-800 p-6 shadow-xl w-full max-w-4xl dark:text-gray-100 backdrop:backdrop-blur-md"
    x-data="imagePickerData({{ $gameId }})"
    x-init="
        // Store reference for the global function
        window._imagePickerDialog = $el;
    "
    @load-images="loadImages()"
>
    <x-ui.dialog-header title="Select an Image" />

    <div class="mb-4">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Click on an image to select it, or upload a new one using the editor's upload button.
        </p>
    </div>

    <!-- Loading State -->
    <div x-show="loading" class="flex justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
    </div>

    <!-- Error State -->
    <div x-show="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-4">
        <p class="text-red-700 dark:text-red-400" x-text="error"></p>
    </div>

    <!-- Images Grid -->
    <div x-show="!loading && !error" class="max-h-96 overflow-y-auto">
        <div x-show="images.length === 0" class="text-center py-8">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No images uploaded</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Upload some images first to see them here.</p>
        </div>

        <div x-show="images.length > 0" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            <template x-for="image in images" :key="image.path">
                <div class="group relative overflow-hidden rounded-lg border-2 border-transparent hover:border-blue-500 transition-colors cursor-pointer">
                    <div class="w-full h-32 bg-gray-100 dark:bg-gray-700 flex items-center justify-center overflow-hidden rounded">
                        <img
                            :src="image.url"
                            :alt="image.name"
                            class="max-w-full max-h-full object-contain"
                            @click="selectImage(image.url)"
                        />
                    </div>
                    <div class="absolute inset-0 group-hover:bg-opacity-20 transition-opacity flex items-center justify-center">
                        <div class="opacity-0 group-hover:opacity-100 transition-opacity flex space-x-2">
                            <button
                                @click.stop="selectImage(image.url)"
                                class="bg-blue-500 hover:bg-blue-600 text-white p-2 rounded-full transition-colors"
                                title="Select Image"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </button>
                            <button
                                @click.stop="deleteImage(image.path)"
                                class="bg-red-500 hover:bg-red-600 text-white p-2 rounded-full transition-colors"
                                title="Delete Image"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="absolute bottom-0 left-0 right-0 bg-black bg-opacity-75 text-white text-xs p-2 opacity-0 group-hover:opacity-100 transition-opacity">
                        <div class="truncate" x-text="image.name"></div>
                        <div x-text="formatFileSize(image.size)"></div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <x-ui.dialog-footer />
</dialog>
