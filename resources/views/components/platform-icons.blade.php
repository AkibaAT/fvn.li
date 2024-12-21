@props(['platforms' => []])

<div class="flex space-x-1">
    @if($platforms['windows'] ?? false)
        <button
            wire:click="toggleFilter('platform', 'windows')"
            title="Filter by Windows"
            @class([
                'text-base hover:opacity-80 rounded px-0.5',
                'bg-blue-500 shadow-sm' => in_array('windows', $selectedPlatforms ?? []),
            ])>
            🪟
        </button>
    @endif
    @if($platforms['linux'] ?? false)
        <button
            wire:click="toggleFilter('platform', 'linux')"
            title="Filter by Linux"
            @class([
                'text-base hover:opacity-80 rounded px-0.5',
                'bg-blue-500 shadow-sm' => in_array('linux', $selectedPlatforms ?? []),
            ])>
            🐧
        </button>
    @endif
    @if($platforms['mac'] ?? false)
        <button
            wire:click="toggleFilter('platform', 'mac')"
            title="Filter by Mac"
            @class([
                'text-base hover:opacity-80 rounded px-0.5',
                'bg-blue-500 shadow-sm' => in_array('mac', $selectedPlatforms ?? []),
            ])>
            🍎
        </button>
    @endif
    @if($platforms['android'] ?? false)
        <button
            wire:click="toggleFilter('platform', 'android')"
            title="Filter by Android"
            @class([
                'text-base hover:opacity-80 rounded px-0.5',
                'bg-blue-500 shadow-sm' => in_array('android', $selectedPlatforms ?? []),
            ])>
            📱
        </button>
    @endif
    @if($platforms['web'] ?? false)
        <button
            wire:click="toggleFilter('platform', 'web')"
            title="Filter by Web"
            @class([
                'text-base hover:opacity-80 rounded px-0.5',
                'bg-blue-500 shadow-sm' => in_array('web', $selectedPlatforms ?? []),
            ])>
            🌐
        </button>
    @endif
</div>
