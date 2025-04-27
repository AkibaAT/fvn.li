<div
    x-data="{
        isChecking: false,
        message: '',
        existingBuild: null,

        checkEligibility() {
            this.isChecking = true;
            this.message = '';

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
                this.isChecking = false;

                if (data.eligible) {
                    // Check if we already have a completed build for this version
                    this.checkExistingBuild();
                } else {
                    this.message = data.message;
                }
            })
            .catch(error => {
                console.error('Error checking eligibility:', error);
                this.message = 'Error checking eligibility. Please try again.';
                this.isChecking = false;
            });
        },

        checkExistingBuild() {
            fetch('{{ route('user.android-builds.check-existing') }}', {
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
                // Always show the dialog, but pass the existing build info if it exists
                const dialogElement = document.getElementById('android-build-dialog-{{ $game->id }}');

                if (data.exists) {
                    // Store the build info in a global variable that the dialog can access
                    window.existingAndroidBuild_{{ $game->id }} = data.build;
                } else {
                    window.existingAndroidBuild_{{ $game->id }} = null;
                }

                // Show the dialog
                dialogElement.showModal();
            })
            .catch(error => {
                console.error('Error checking existing build:', error);
                // If there's an error, just show the build dialog
                document.getElementById('android-build-dialog-{{ $game->id }}').showModal();
            });
        }
    }"
    class="inline-block"
>
    <button
        type="button"
        @click="checkEligibility"
        :disabled="isChecking"
        class="h-[38px] inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 active:bg-blue-700 focus:outline-none focus:border-blue-700 focus:ring focus:ring-blue-300 disabled:opacity-25 transition"
    >
        <i class="icon-android text-[#3DDC84] mr-1 text-lg"></i>
        <span x-text="isChecking ? 'Please wait...' : 'Build for Android'"></span>
        <span x-show="isChecking" class="ml-1 inline-block h-4 w-4 animate-spin rounded-full border-2 border-solid border-current border-r-transparent align-[-0.125em]"></span>
    </button>

    <div x-show="!isChecking && message && !existingBuild && message !== 'Checking eligibility...'" x-transition class="mt-2 text-sm text-red-600">
        <p x-text="message"></p>
    </div>

    @include('games.components.android-build-dialog', ['game' => $game, 'version' => $version])
</div>
