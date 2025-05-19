@props(['platforms' => [], 'selectedPlatforms' => [], 'clickable' => true])

<div class="flex gap-2 text-lg">
    @foreach ([
        'windows' => ['icon' => 'icon-windows', 'color' => 'text-[#00A4EF]'],
        'linux'   => ['icon' => 'icon-linux', 'color' => 'dark:text-[#F0B90B]'],
        'mac'     => ['icon' => 'icon-apple', 'color' => 'text-[#555555] dark:text-gray-300'],
        'android' => ['icon' => 'icon-android', 'color' => 'text-[#3DDC84]'],
        'web'     => ['icon' => 'icon-web', 'color' => 'text-[#4285F4]']
    ] as $platform => $config)
        @if ($platforms[$platform] ?? false)
            @if ($clickable)
                <button
                    wire:click="toggleFilter('platform', '{{ addslashes($platform) }}')"
                    title="Filter by {{ ucfirst($platform) }}"
                    @class([
                        'px-1 rounded-sm transition-opacity duration-150 cursor-pointer',
                        'ring-2 ring-blue-500 dark:ring-blue-400' => in_array($platform, $selectedPlatforms ?? []),
                    ])>
                    <i class="{{ $config['icon'] }} {{ $config['color'] }} hover:opacity-70"></i>
                </button>
            @else
                <i class="{{ $config['icon'] }} {{ $config['color'] }}" title="{{ ucfirst($platform) }}"></i>
            @endif
        @endif
    @endforeach
</div>
