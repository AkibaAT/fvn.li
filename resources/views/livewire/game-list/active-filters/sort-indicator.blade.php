@php
    $sortLabels = [
        'version_published_at' => 'Latest Update',
        'initially_published_at' => 'Initial Release',
        'stats_words' => 'Word Count',
        'rating_count' => 'Review Count',
        'name' => 'Name'
    ];
    $isDefaultSort = $sortField === 'version_published_at' && $sortDirection === 'desc';
@endphp
@if(!$isDefaultSort)
    <button
        wire:click="resetSort"
        class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300"
    >
        Sorted by: {{ $sortLabels[$sortField] }} {{ $sortDirection === 'asc' ? '↑' : '↓' }}
        <span class="ml-2">&times;</span>
    </button>
@endif
