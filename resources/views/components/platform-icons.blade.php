@props(['platforms' => [], 'selectedPlatforms' => []])

<div class="flex gap-2 text-lg">
    @if ($platforms['windows'] ?? false)
        <button
            wire:click="toggleFilter('platform', 'windows')"
            title="Filter by Windows"
            @class([
                'px-1 rounded',
                'ring-2 ring-blue-500 dark:ring-blue-400' => in_array('windows', $selectedPlatforms ?? []),
            ])>
            <i class="fa-brands fa-windows text-[#00A4EF] hover:text-[#00A4EF]/50" title="Windows"></i>
        </button>
    @endif
    @if ($platforms['linux'] ?? false)
        <button
            wire:click="toggleFilter('platform', 'linux')"
            title="Filter by Linux"
            @class([
                'px-1 rounded',
                'ring-2 ring-blue-500 dark:ring-blue-400' => in_array('linux', $selectedPlatforms ?? []),
            ])>
            <i class="fa-brands fa-linux hover:text-black/50 dark:text-[#F0B90B] dark:hover:text-[#F0B90B]/80" title="Linux"></i>
        </button>
    @endif
    @if ($platforms['mac'] ?? false)
        <button
            wire:click="toggleFilter('platform', 'mac')"
            title="Filter by Mac"
            @class([
                'px-1 rounded',
                'ring-2 ring-blue-500 dark:ring-blue-400' => in_array('mac', $selectedPlatforms ?? []),
            ])>
            <i class="fa-brands fa-apple text-[#555555] hover:text-[#555555]/50 dark:text-gray-300 dark:hover:text-gray-300/80" title="Mac"></i>
        </button>
    @endif
    @if ($platforms['android'] ?? false)
        <button
            wire:click="toggleFilter('platform', 'android')"
            title="Filter by Android"
            @class([
                'px-1 rounded',
                'ring-2 ring-blue-500 dark:ring-blue-400' => in_array('android', $selectedPlatforms ?? []),
            ])>
            <i class="fa-brands fa-android text-[#3DDC84] hover:text-[#3DDC84]/50" title="Android"></i>
        </button>
    @endif
    @if ($platforms['web'] ?? false)
        <button
            wire:click="toggleFilter('platform', 'web')"
            title="Filter by Web"
            @class([
                'px-1 rounded',
                'ring-2 ring-blue-500 dark:ring-blue-400' => in_array('web', $selectedPlatforms ?? []),
            ])>
            <i class="fa-solid fa-globe text-[#4285F4] hover:text-[#4285F4]/50" title="Web"></i>
        </button>
    @endif
</div>
