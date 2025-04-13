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
        ],
        'gamejam' => [
            'items' => $selectedGameJams,
            'class' => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
            'label' => fn($item) => $gameJams[$item] ?? '???'
        ]
    ];
@endphp

@foreach ($filterConfigs as $type => $config)
    @php
        // For game jams, sort the items alphabetically by their labels
        $items = $config['items'];
        if ($type === 'gamejam' && count($items) > 0) {
            // Create an array of [item, label] pairs
            $itemsWithLabels = [];
            foreach ($items as $item) {
                $itemsWithLabels[] = [$item, $config['label']($item)];
            }

            // Sort by label
            usort($itemsWithLabels, function($a, $b) {
                return strcmp($a[1], $b[1]);
            });

            // Extract just the items in the new order
            $items = array_map(function($pair) {
                return $pair[0];
            }, $itemsWithLabels);
        }
    @endphp

    @foreach ($items as $item)
        @php
            $decodedItem = $this->decodeFilterValue($item);
        @endphp
        <button wire:click="toggleFilter('{{ $type }}', '{{ $item }}')"
                class="inline-flex items-center px-3 py-1 rounded-full text-sm {{ $config['class'] }}">
            @if ($type === 'language' && isset($languages[$decodedItem]))
                <span class="fi fi-{{ $languages[$decodedItem]['flag_code'] }} rounded-xs mr-2"></span>
            @endif
            {{ $config['label']($item) }}
            <span class="ml-2">&times;</span>
        </button>
    @endforeach
@endforeach

@if ($showHidden)
    <button wire:click="$toggle('showHidden')"
            class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300">
        Including Unlisted Games
        <span class="ml-2">&times;</span>
    </button>
@endif

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

@if ($showPaid)
    <button wire:click="$toggle('showPaid')"
            class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300">
        Paid Games
        <span class="ml-2">&times;</span>
    </button>
@endif

@if ($showFree)
    <button wire:click="$toggle('showFree')"
            class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300">
        Free Games
        <span class="ml-2">&times;</span>
    </button>
@endif

@if ($showDemo)
    <button wire:click="$toggle('showDemo')"
            class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300">
        Has Demo
        <span class="ml-2">&times;</span>
    </button>
@endif
