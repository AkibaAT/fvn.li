{{-- resources/views/livewire/rater-detail.blade.php --}}
<div class="min-h-screen bg-gray-100 dark:bg-gray-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-4 flex items-center justify-between sticky top-0 z-10 bg-gray-100 dark:bg-gray-900 py-4">
            <a href="{{ route('games.index') }}"
               class="inline-flex items-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Game List
            </a>
        </div>

        {{-- Static header with stats --}}
        <x-rater-stats :rater-id="$rater->id" />

        {{-- Dynamic ratings list --}}
        <livewire:ratings-list :rater-id="$rater->id" />

        @include('components.bookmarklet')
    </div>

    {{-- Rating history dialog (moved outside the main content) --}}
    <livewire:rating-history-dialog />

    <script>
        document.addEventListener('show-rating-history', event => {
            document.getElementById('rating-history').showModal();
        });

        // Close dialog when clicking outside
        document.querySelectorAll('dialog').forEach(dialog => {
            dialog.addEventListener('click', (e) => {
                if (e.target === e.currentTarget) {
                    e.currentTarget.close();
                }
            });
        });
    </script>

    @include('components.meta-data-refresh')
</div>
