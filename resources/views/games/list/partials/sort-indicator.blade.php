@php
    $isDefaultSort = $this->isDefaultSort($sortField, $sortDirection);
@endphp
@if (!$isDefaultSort)
    <button
        wire:click="resetSort"
        class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300"
    >
        Sorted by: {{ $this->getSortLabel($sortField) }} {{ $sortDirection === 'asc' ? '↑' : '↓' }}
        <span class="ml-2">&times;</span>
    </button>
@endif
