<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg">
    <div class="p-6">
        <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Request VN Addition</h2>
        <p class="text-gray-600 dark:text-gray-400 mb-6">
            Submit itch.io URLs for visual novels you'd like to see added to the site. You can submit multiple URLs at once, one per line.
        </p>

        <form wire:submit="submitRequests">
            <div class="mb-4">
                <label for="urls" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Itch.io URLs
                </label>
                <textarea
                    wire:model="urls"
                    id="urls"
                    rows="6"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                    placeholder="https://developer.itch.io/game-name&#10;https://anotherdeveloper.itch.io/another-game&#10;..."
                ></textarea>
                @error('urls')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
                @error('auth')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex gap-3">
                <button
                    type="submit"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>Submit Requests</span>
                    <span wire:loading>Submitting...</span>
                </button>

                <button
                    type="button"
                    wire:click="clearForm"
                    class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500 transition-colors"
                >
                    Clear
                </button>
            </div>
        </form>

        <!-- Success Message -->
        @if ($showSuccessMessage && !empty($submissionResults))
            <div class="mt-6 p-4 bg-green-100 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                <div class="flex items-start">
                    <svg class="w-5 h-5 text-green-600 dark:text-green-400 mt-0.5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <div>
                        <h3 class="text-green-800 dark:text-green-400 font-medium">Requests Submitted Successfully!</h3>
                        <div class="mt-2 text-green-700 dark:text-green-300 text-sm">
                            @if ($submissionResults['success_count'] > 0)
                                <p>✓ {{ $submissionResults['success_count'] }} new request(s) submitted</p>
                            @endif
                            @if ($submissionResults['duplicate_count'] > 0)
                                <p>ℹ {{ $submissionResults['duplicate_count'] }} URL(s) already requested by you</p>
                            @endif
                            @if ($submissionResults['already_exists_count'] > 0)
                                <p>ℹ {{ $submissionResults['already_exists_count'] }} game(s) already exist on the site</p>
                            @endif
                            @if ($submissionResults['invalid_count'] > 0)
                                <p>⚠ {{ $submissionResults['invalid_count'] }} invalid URL(s) skipped</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Error Messages -->
        @if (!empty($submissionResults['errors']))
            <div class="mt-6 p-4 bg-red-100 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                <h3 class="text-red-800 dark:text-red-400 font-medium mb-2">Some errors occurred:</h3>
                <ul class="text-red-700 dark:text-red-300 text-sm space-y-1">
                    @foreach ($submissionResults['errors'] as $error)
                        <li>• {{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Help Text -->
        <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
            <h3 class="text-blue-800 dark:text-blue-400 font-medium mb-2">Guidelines:</h3>
            <ul class="text-blue-700 dark:text-blue-300 text-sm space-y-1">
                <li>• Only itch.io URLs are accepted (e.g., https://developer.itch.io/game-name)</li>
                <li>• Submit one URL per line for bulk requests</li>
                <li>• Maximum 50 URLs per submission</li>
                <li>• Games already on the site will be automatically filtered out</li>
                <li>• Duplicate requests are automatically handled</li>
                <li>• You'll be able to track the status of your requests in your dashboard</li>
            </ul>
        </div>
    </div>
</div>
