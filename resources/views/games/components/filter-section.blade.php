@props(['title', 'type', 'items', 'selected', 'buttonClass'])

<div>
    <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $title }}</div>
    <div class="flex flex-wrap gap-2">
        @foreach ($items as $value => $label)
            <x-ui.filter-button
                wire:click="toggleFilter('{{ $type }}', '{{ addslashes($value) }}')"
                :active="in_array($value, $selected)"
                :type="$buttonClass"
            >
                {{ $label }}
            </x-ui.filter-button>
        @endforeach
    </div>
</div>
