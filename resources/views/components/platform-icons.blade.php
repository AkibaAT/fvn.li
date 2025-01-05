@props(['platforms' => [], 'selectedPlatforms' => [], 'clickable' => true])

<div class="flex gap-2 text-lg">
    @foreach ([
        'windows' => ['icon' => 'fa-brands fa-windows', 'color' => 'text-[#00A4EF]'],
        'linux' => ['icon' => 'fa-brands fa-linux', 'color' => 'dark:text-[#F0B90B]'],
        'mac' => ['icon' => 'fa-brands fa-apple', 'color' => 'text-[#555555] dark:text-gray-300'],
        'android' => ['icon' => 'fa-brands fa-android', 'color' => 'text-[#3DDC84]'],
        'web' => ['icon' => 'fa-solid fa-globe', 'color' => 'text-[#4285F4]']
    ] as $platform => $config)
        @if ($platforms[$platform] ?? false)
            @if ($clickable)
                <button
                    wire:click="toggleFilter('platform', '{{ $platform }}')"
                    title="Filter by {{ ucfirst($platform) }}"
                    @class([
                        'px-1 rounded',
                        'ring-2 ring-blue-500 dark:ring-blue-400' => in_array($platform, $selectedPlatforms ?? []),
                    ])>
                    <i class="{{ $config['icon'] }} {{ $config['color'] }} hover:opacity-50"></i>
                </button>
            @else
                <i class="{{ $config['icon'] }} {{ $config['color'] }}" title="{{ ucfirst($platform) }}"></i>
            @endif
        @endif
    @endforeach
</div>
