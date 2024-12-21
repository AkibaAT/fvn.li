@props(['title', 'type', 'items', 'selected', 'buttonClass'])

<div>
    <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $title }}</div>
    <div class="flex flex-wrap gap-2">
        @foreach($items as $value => $label)
            @php $encodedValue = $this->encodeFilterValue($value); @endphp
            <x-filter-button
                wire:click="toggleFilter('{{ $type }}', '{{ $encodedValue }}')"
                :active="in_array($encodedValue, $selected)"
                :type="$buttonClass"
            >
                {{ $label }}
            </x-filter-button>
        @endforeach
    </div>
</div>
