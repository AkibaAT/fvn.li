@php
    $filterConfigs = [
        'platform' => [
            'items' => $selectedPlatforms,
            'class' => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
            'label' => fn($item) => $platforms[$this->decodeFilterValue($item)]
        ],
        'status' => [
            'items' => $selectedStatuses,
            'class' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
            'label' => fn($item) => $this->decodeFilterValue($item)
        ],
        'engine' => [
            'items' => $selectedEngines,
            'class' => 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300',
            'label' => fn($item) => $this->decodeFilterValue($item)
        ]
    ];
@endphp

@foreach($filterConfigs as $type => $config)
    @foreach($config['items'] as $item)
        <button wire:click="toggleFilter('{{ $type }}', '{{ $item }}')"
                class="inline-flex items-center px-3 py-1 rounded-full text-sm {{ $config['class'] }}">
            {{ $config['label']($item) }}
            <span class="ml-2">&times;</span>
        </button>
    @endforeach
@endforeach

@if($nsfw)
    <button wire:click="$toggle('nsfw')"
            class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300">
        NSFW
        <span class="ml-2">&times;</span>
    </button>
@endif
