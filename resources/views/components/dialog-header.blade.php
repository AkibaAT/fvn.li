@props(['title'])

<div class="flex justify-between items-center mb-4">
    <h3 class="text-lg font-medium">{{ $title }}</h3>
    <button
        @click="$el.closest('dialog').close()"
        class="text-gray-400 hover:text-gray-500"
    >
        <span class="sr-only">Close</span>
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>
</div>
