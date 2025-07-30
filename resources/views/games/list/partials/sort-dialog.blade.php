<dialog
    wire:ignore.self
    id="sort-modal"
    class="m-auto rounded-lg bg-white dark:bg-gray-800 p-6 shadow-xl w-full max-w-sm dark:text-gray-100 backdrop:backdrop-blur-md"
>
    <x-ui.dialog-header title="Sort Games"/>

    <div class="space-y-2">
        @foreach ($this->getAvailableSortFieldsWithLabels() as $field => $label)
            <button
                wire:click="sortBy('{{ $field }}')"
                class="w-full text-left px-4 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center justify-between {{ $sortField === $field ? 'bg-gray-50 dark:bg-gray-700' : '' }}"
            >
                <span>{{ $label }}</span>
                @if ($sortField === $field)
                    <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                @endif
            </button>
        @endforeach
    </div>

    <x-ui.dialog-footer/>
</dialog>
