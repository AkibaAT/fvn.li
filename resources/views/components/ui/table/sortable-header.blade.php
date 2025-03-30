@props(['field', 'currentField', 'direction'])

<th {{ $attributes->merge(['class' => 'px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100']) }}>
    <button wire:click="sortBy('{{ $field }}')" class="group inline-flex items-center">
        {{ $slot }}
        @if ($currentField === $field)
            <span class="ml-1">{{ $direction === 'asc' ? '↑' : '↓' }}</span>
        @endif
    </button>
</th>
