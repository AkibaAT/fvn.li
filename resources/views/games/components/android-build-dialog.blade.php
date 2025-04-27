<dialog
    id="android-build-dialog-{{ $game->id }}"
    class="m-auto rounded-lg bg-white dark:bg-gray-800 p-6 shadow-xl w-full max-w-lg dark:text-gray-100 backdrop:backdrop-blur-md"
    x-data="{
        isRequesting: false,
        message: '',
        buildId: null,
        buildStatus: null,
        downloadUrl: null,
        existingBuild: null,

        init() {
            // Check if there's an existing build stored in the global variable
            if (window.existingAndroidBuild_{{ $game->id }}) {
                this.existingBuild = window.existingAndroidBuild_{{ $game->id }};
                this.buildStatus = 'completed';
                this.downloadUrl = this.existingBuild.download_url;
                this.message = 'An Android APK is already available for this version.';
                console.log('Existing build found:', this.existingBuild);
            } else {
                console.log('No existing build found');
            }
        },

        requestBuild() {
            // If we already have a build, just return
            if (this.existingBuild) {
                return;
            }

            this.isRequesting = true;
            this.message = '';

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

                    // Check if we got an existing completed build
                    if (data.status === 'completed' && data.existing) {
                        this.buildStatus = 'completed';
                        this.downloadUrl = data.download_url;
                        this.message = data.message || 'An Android APK is already available for this version.';
                        this.existingBuild = {
                            id: data.build_id,
                            download_url: data.download_url
                        };
                    }
                    // Check if we got an existing pending or processing build
                    else if ((data.status === 'pending' || data.status === 'processing') && data.existing) {
                        this.buildStatus = data.status;
                        this.message = data.message || (data.status === 'pending'
                            ? 'Your build request is in the queue and will be processed soon.'
                            : 'Your build is currently being processed. This may take several minutes.');
                        // Start checking status
                        this.checkStatus();
                    }
                    // New build request
                    else {
                        this.buildStatus = 'pending';
                        this.message = 'Build requested successfully! You will be notified when it\'s ready.';
                        // Start checking status
                        this.checkStatus();
                    }
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
>
    <x-ui.dialog-header title="Build Android APK for {{ $game->name }}"/>

    <div class="mt-2">
        <p class="text-sm text-gray-500 dark:text-gray-300">
            This will create an Android APK file for {{ $game->name }} that you can install on your Android device.
        </p>

        <div class="mt-4 rounded-md bg-yellow-50 p-4 dark:bg-yellow-900/30">
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
                            <li>This will create an <strong>unofficial</strong> APK file that you can install on your Android device.</li>
                            <li>The build process may take several minutes to complete.</li>
                            <li>You will need to enable "Install from unknown sources" on your device to install the APK.</li>
                            <li>This APK is not signed by the original developer and is provided for personal use only.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 rounded-md bg-red-50 p-4 dark:bg-red-900/30">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800 dark:text-red-300">Important disclaimer</h3>
                    <div class="mt-2 text-sm text-red-700 dark:text-red-200">
                        <p>Please do not leave negative reviews or bug reports for the game due to issues with this unofficial Android build. These builds are provided as a convenience and are not officially supported by the game developers.</p>
                    </div>
                </div>
            </div>
        </div>

        <div x-show="buildStatus === 'pending' || buildStatus === 'processing'" class="mt-4 text-center">
            <p class="text-sm text-blue-600 dark:text-blue-400">
                <svg class="mx-auto h-8 w-8 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span x-text="message" class="mt-2 block"></span>
            </p>
        </div>

        <div x-show="buildStatus === 'completed' || existingBuild" class="mt-4 rounded-md bg-green-50 p-4 dark:bg-green-900/30">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800 dark:text-green-300" x-text="existingBuild ? 'An Android APK is already available for this version.' : 'Build completed successfully!'"></p>
                </div>
            </div>
        </div>

        <div x-show="buildStatus === 'failed'" class="mt-4 rounded-md bg-red-50 p-4 dark:bg-red-900/30">
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

    <div class="mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
        <!-- Build Now button - show when no build status and not an existing build -->
        <button
            x-show="buildStatus === null && !existingBuild"
            type="button"
            @click="requestBuild"
            :disabled="isRequesting || existingBuild"
            class="inline-flex w-full justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 sm:col-start-2 sm:text-sm"
        >
            <span x-text="isRequesting ? 'Please wait...' : 'Build Now'"></span>
            <span x-show="isRequesting" class="ml-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-solid border-current border-r-transparent align-[-0.125em]"></span>
        </button>

        <!-- Download button - show when there's an existing build -->
        <a
            x-show="existingBuild"
            :href="existingBuild ? existingBuild.download_url : downloadUrl"
            class="inline-flex w-full justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:col-start-2 sm:text-sm"
        >
            <i class="icon-android text-[#3DDC84] mr-2 text-lg"></i>
            Download APK
        </a>

        <!-- Download button - show when build is completed but not an existing build -->
        <a
            x-show="buildStatus === 'completed' && !existingBuild"
            :href="downloadUrl"
            class="inline-flex w-full justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:col-start-2 sm:text-sm"
        >
            <i class="icon-android text-[#3DDC84] mr-2 text-lg"></i>
            Download APK
        </a>

        <!-- Close button - always show -->
        <button
            type="button"
            @click="$el.closest('dialog').close()"
            class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600 sm:col-start-1 sm:mt-0 sm:text-sm"
        >
            Close
        </button>
    </div>
</dialog>

<script>
    // Close dialog when clicking outside
    document.getElementById('android-build-dialog-{{ $game->id }}').addEventListener('click', (e) => {
        if (e.target === e.currentTarget) {
            e.currentTarget.close();
        }
    });
</script>
