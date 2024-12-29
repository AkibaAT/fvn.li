@php
    $filterConfigs = [
        'platform' => [
            'items' => $selectedPlatforms,
            'class' => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
            'label' => fn($item) => $platforms[rawurldecode($this->decodeFilterValue($item))]
        ],
        'status' => [
            'items' => $selectedStatuses,
            'class' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
            'label' => fn($item) => rawurldecode($this->decodeFilterValue($item))
        ],
        'engine' => [
            'items' => $selectedEngines,
            'class' => 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300',
            'label' => fn($item) => rawurldecode($this->decodeFilterValue($item))
        ],
        'language' => [
            'items' => $selectedLanguages,
            'class' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300',
            'label' => fn($item) => $languages[$this->decodeFilterValue($item)]['ref_name'] ?? '???'
        ]
    ];
@endphp

@foreach ($filterConfigs as $type => $config)
    @foreach ($config['items'] as $item)
        @php
            $decodedItem = $this->decodeFilterValue($item);
        @endphp
        <button wire:click="toggleFilter('{{ $type }}', '{{ $item }}')"
                class="inline-flex items-center px-3 py-1 rounded-full text-sm {{ $config['class'] }}">
            @if ($type === 'language' && isset($languages[$decodedItem]))
                <span class="fi fi-{{ $languages[$decodedItem]['flag_code'] }} rounded-sm mr-2"></span>
            @endif
            {{ $config['label']($item) }}
            <span class="ml-2">&times;</span>
        </button>
    @endforeach
@endforeach

@if ($nsfw)
    <button wire:click="$toggle('nsfw')"
            class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300">
        NSFW
        <span class="ml-2">&times;</span>
    </button>
@endif

@if ($sfw)
    <button wire:click="$toggle('sfw')"
            class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300">
        SFW
        <span class="ml-2">&times;</span>
    </button>
@endif
