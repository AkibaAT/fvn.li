<div
    x-data="{
        showModal: false,
        isEligible: false,
        isChecking: false,
        isRequesting: false,
        message: '',
        buildId: null,
        buildStatus: null,
        downloadUrl: null,

        checkEligibility() {
            this.isChecking = true;
            this.message = 'Checking eligibility...';

            fetch('{{ route('user.android-builds.check-eligibility') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    game_id: {{ $game->id }},
                    version_id: {{ $version->id ?? 'null' }}
                })
            })
            .then(response => response.json())
            .then(data => {
                this.isEligible = data.eligible;
                this.message = data.message;
                this.isChecking = false;

                if (this.isEligible) {
                    this.showModal = true;
                }
            })
            .catch(error => {
                console.error('Error checking eligibility:', error);
                this.message = 'Error checking eligibility. Please try again.';
                this.isChecking = false;
            });
        },

        requestBuild() {
            this.isRequesting = true;
            this.message = 'Requesting build...';

            fetch('{{ route('user.android-builds.request') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    game_id: {{ $game->id }},
                    version_id: {{ $version->id ?? 'null' }}
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.buildId = data.build_id;
                    this.buildStatus = 'pending';
                    this.message = 'Build requested successfully! You will be notified when it\'s ready.';

                    // Start checking status
                    this.checkStatus();
                } else {
                    this.message = data.message || 'Failed to request build. Please try again.';
                }
                this.isRequesting = false;
            })
            .catch(error => {
                console.error('Error requesting build:', error);
                this.message = 'Error requesting build. Please try again.';
                this.isRequesting = false;
            });
        },

        checkStatus() {
            if (!this.buildId) return;

            fetch('{{ route('user.android-builds.status') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    build_id: this.buildId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.buildStatus = data.status;
                    this.message = data.message;

                    if (data.status === 'completed') {
                        this.downloadUrl = data.download_url;
                    } else if (data.status === 'pending' || data.status === 'processing') {
                        // Check again in 10 seconds
                        setTimeout(() => this.checkStatus(), 10000);
                    }
                }
            })
            .catch(error => {
                console.error('Error checking status:', error);
            });
        }
    }"
    class="mt-4"
>
    <button
        type="button"
        @click="checkEligibility"
        :disabled="isChecking"
        class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="-ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>
        <span x-text="isChecking ? 'Checking...' : 'Build for Android'"></span>
    </button>

    <div x-show="!isEligible && message && !isChecking" x-transition class="mt-2 text-sm text-red-600">
        <p x-text="message"></p>
    </div>

    <!-- Modal -->
    <div
        x-show="showModal"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto"
        x-cloak
    >
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

        <div
            x-show="showModal"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all dark:bg-gray-800 sm:my-8 sm:w-full sm:max-w-lg sm:p-6"
        >
            <div>
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600 dark:text-blue-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <div class="mt-3 text-center sm:mt-5">
                    <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">
                        Build Android APK for {{ $game->name }}
                    </h3>
                    <div class="mt-2">
                        <p class="text-sm text-gray-500 dark:text-gray-300">
                            This will create an Android APK file for {{ $game->name }} that you can install on your Android device.
                        </p>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-300">
                            The build process may take several minutes. You'll be notified when it's ready.
                        </p>

                        <div x-show="buildStatus === null" class="mt-4 rounded-md bg-yellow-50 p-4 dark:bg-yellow-900/30">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-300">Important notes</h3>
                                    <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-200">
                                        <ul class="list-inside list-disc space-y-1">
                                            <li>This is an unofficial build not provided by the game developer</li>
                                            <li>You'll need to enable "Install from unknown sources" on your device</li>
                                            <li>Some games may not work perfectly on all devices</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div x-show="buildStatus !== null" class="mt-4 text-center">
                            <p x-show="buildStatus === 'pending' || buildStatus === 'processing'" class="text-sm text-blue-600 dark:text-blue-400">
                                <svg class="mx-auto h-8 w-8 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span x-text="message" class="mt-2 block"></span>
                            </p>

                            <div x-show="buildStatus === 'completed'" class="rounded-md bg-green-50 p-4 dark:bg-green-900/30">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-green-800 dark:text-green-300">
                                            Build completed successfully!
                                        </p>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <a :href="downloadUrl" class="inline-flex justify-center rounded-md border border-transparent bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                                        Download APK
                                    </a>
                                </div>
                            </div>

                            <div x-show="buildStatus === 'failed'" class="rounded-md bg-red-50 p-4 dark:bg-red-900/30">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-red-800 dark:text-red-300">
                                            Build failed
                                        </p>
                                        <p class="mt-2 text-sm text-red-700 dark:text-red-200" x-text="message"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                <button
                    x-show="buildStatus === null"
                    type="button"
                    @click="requestBuild"
                    :disabled="isRequesting"
                    class="inline-flex w-full justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 sm:col-start-2 sm:text-sm"
                >
                    <span x-text="isRequesting ? 'Requesting...' : 'Build Now'"></span>
                </button>
                <button
                    type="button"
                    @click="showModal = false"
                    class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600 sm:col-start-1 sm:mt-0 sm:text-sm"
                >
                    Close
                </button>
                <a
                    x-show="buildStatus !== null"
                    href="{{ route('user.android-builds.index') }}"
                    class="inline-flex w-full justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:col-start-2 sm:text-sm"
                >
                    View All Builds
                </a>
            </div>
        </div>
    </div>
</div>
