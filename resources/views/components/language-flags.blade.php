@props(['languages' => collect(), 'selectedLanguages' => [], 'showLabels' => false, 'clickable' => true])

<div class="flex flex-wrap gap-2">
    @foreach($languages as $language)
        @php
            $encodedCode = $this->encodeFilterValue($language['iso_code']);
            $isSelected = isset($encodedSelectedLanguages[$encodedCode]);
            $isFiltered = !empty($selectedLanguages) && $isSelected;
        @endphp
        @if($clickable)
            <button
                wire:click="toggleFilter('language', '{{ $encodedCode }}')"
                @class([
                    'inline-flex items-center gap-1 px-1 py-0.5 rounded transition-all duration-150',
                    'hover:bg-gray-100 dark:hover:bg-gray-700' => !$isSelected,
                    'ring-2 ring-blue-500 dark:ring-blue-400' => $isSelected,
                ])
                title="{{ $language['ref_name'] }}"
            >
                <span class="fi fi-{{ $language['flag_code'] }} rounded-sm {{ $isFiltered ? 'opacity-100 scale-110' : 'opacity-80' }}"></span>
                @if($showLabels)
                    <span class="text-xs">{{ $language['ref_name'] }}</span>
                @endif
            </button>
        @else
            <div
                class="inline-flex items-center gap-1 px-1 py-0.5"
                title="{{ $language['ref_name'] }}"
            >
                <span class="fi fi-{{ $language['flag_code'] }} rounded-sm opacity-80"></span>
                @if($showLabels)
                    <span class="text-xs">{{ $language['ref_name'] }}</span>
                @endif
            </div>
        @endif
    @endforeach
</div>
