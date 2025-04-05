<x-layouts.app :metaTags="$metaTags">
    <livewire:vn-list-show :vnList="$vnList" :isOwner="$isOwner" />

    @push('scripts')
        <style>
            .sortable-ghost {
                @apply bg-gray-100 dark:bg-gray-600;
            }
            .sortable-drag {
                @apply opacity-90;
            }
        </style>
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
        @vite('resources/js/toggle-notifications.ts')
    @endpush
</x-layouts.app>
