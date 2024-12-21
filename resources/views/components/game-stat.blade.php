@props(['label', 'value', 'type' => null, 'isFilter' => true])

<div>
    <span class="text-gray-400">{{ $label }}:</span>
    @if($isFilter && $type)
        <button wire:click="toggleFilter('{{ $type }}', '{{ $this->encodeFilterValue($value) }}')"
            @class([
                'ml-1 hover:text-blue-400',
                'text-blue-400 font-medium' => in_array($this->encodeFilterValue($value), $selectedStatuses),
                'text-gray-200' => !in_array($this->encodeFilterValue($value), $selectedStatuses),
            ])>
            {{ $value }}
        </button>
    @else
        <span class="ml-1 text-gray-200">{{ $value }}</span>
    @endif
</div>
