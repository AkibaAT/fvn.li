@props(['game'])

<div class="flex space-x-3">
    <img
        src="{{ $game->thumb_url }}"
        alt="{{ $game->name }}"
        class="h-20 w-24 min-w-24 object-cover rounded"
    >
    <div class="min-w-0 flex flex-col">
        <div class="flex items-center space-x-2">
            <a href="{{ $game->url }}" target="_blank" class="text-sm font-medium text-gray-900 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-400">
                {{ $game->name }}
            </a>
            <x-platform-icons
                :platforms="[
                    'windows' => $game->platform_windows,
                    'linux' => $game->platform_linux,
                    'mac' => $game->platform_mac,
                    'android' => $game->platform_android,
                    'web' => $game->platform_web,
                ]"
                :selected-platforms="$selectedPlatforms"
            />
        </div>
        <p class="text-sm text-gray-500 dark:text-gray-400">{!! $game->authors !!}</p>
        @if($game->languages)
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Languages: {{ $game->languages }}
            </p>
        @endif
        @if($game->tags)
            <div class="mt-1 flex flex-wrap gap-1">
                @foreach(explode(',', $game->tags) as $tag)
                    <x-badge>{{ trim($tag) }}</x-badge>
                @endforeach
            </div>
        @endif
    </div>
</div>
