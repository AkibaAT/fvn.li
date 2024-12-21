@props(['game', 'selectedStatuses' => [], 'selectedEngines' => []])

<div class="bg-white/10 dark:bg-gray-800/50 rounded-lg shadow-sm p-4 flex flex-col backdrop-blur-sm">
    <div class="flex gap-4">
        <img
            src="{{ $game->thumb_url }}"
            alt="{{ $game->name }}"
            class="h-24 w-32 object-cover rounded"
        >
        <div class="flex flex-col min-w-0 flex-1">
            <a href="{{ $game->url }}" target="_blank"
               class="text-base font-medium text-gray-100 hover:text-blue-400 line-clamp-2">
                {{ $game->name }}
            </a>
            <p class="text-sm text-gray-300 mt-1">{!! $game->authors !!}</p>
            <div class="mt-2">
                <x-platform-icons :platforms="$game->platforms" />
            </div>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-2 gap-4 text-sm">
        @foreach([
            ['label' => 'Status', 'value' => $game->status, 'type' => 'status'],
            ['label' => 'Engine', 'value' => $game->game_engine, 'type' => 'engine'],
            ['label' => 'Words', 'value' => number_format($game->stats_words), 'isFilter' => false],
            ['label' => 'Reviews', 'value' => $game->rating_count ?? '-', 'isFilter' => false]
        ] as $detail)
            <div>
                <span class="text-gray-400">{{ $detail['label'] }}:</span>
                @if($detail['isFilter'] ?? true)
                    <button wire:click="toggleFilter('{{ $detail['type'] }}', '{{ $this->encodeFilterValue($detail['value']) }}')"
                        @class([
                            'ml-1 hover:text-blue-400',
                            'text-blue-400 font-medium' => in_array($this->encodeFilterValue($detail['value']), $selectedStatuses),
                            'text-gray-200' => !in_array($this->encodeFilterValue($detail['value']), $selectedStatuses),
                        ])>
                        {{ $detail['value'] }}
                    </button>
                @else
                    <span class="ml-1 text-gray-200">{{ $detail['value'] }}</span>
                @endif
            </div>
        @endforeach
    </div>

    @if($game->tags)
        <div class="mt-4 flex flex-wrap gap-1">
            @foreach(explode(',', $game->tags) as $tag)
                <x-badge class="bg-gray-700/50 text-gray-200">{{ trim($tag) }}</x-badge>
            @endforeach
        </div>
    @endif
</div>
