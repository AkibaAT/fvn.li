@php
    // Calculate version statistics
    $currentVersionStats = $currentVersion->getStatsForLanguage('eng');
    $latestVersionStats = $game->latestVersion?->getStatsForLanguage('eng');
    $wordDiff = null;
    if ($currentVersionStats && $latestVersionStats && $currentVersionStats->words !== $latestVersionStats->words) {
        $wordDiff = $latestVersionStats->words - $currentVersionStats->words;
    }
    $hasCurrentStats = $currentVersion->characterStats()->exists();
    $hasLatestStats = $game->latestVersion?->characterStats()->exists();
    $showCompareButton = $hasCurrentStats && $hasLatestStats && $game->latestVersion && $currentVersion->id !== $game->latestVersion->id;
    $showUpdateInfo = $game->latestVersion && $currentVersion->id !== $game->latestVersion->id;
@endphp

@if ($showUpdateInfo)
    <div class="text-xs text-yellow-600 dark:text-yellow-400 mt-1">
        Latest: v{{ $game->latestVersion->version }}
        <span class="text-gray-400 {{ $layout === 'mobile' ? 'ml-1' : '' }}">
            ({{ $game->latestVersion->published_at->format('Y-m-d') }})
        </span>
    </div>
@endif

@if ($wordDiff !== null)
    <div class="text-xs mt-1">
        <span>Words:
            {{ number_format($currentVersionStats->words) }}
        </span>
        <span @class([
            'ml-1',
            'text-green-600 dark:text-green-400' => $wordDiff > 0,
            'text-red-600 dark:text-red-400' => $wordDiff < 0,
        ])>
            ({{ $wordDiff > 0 ? '+' : '' }}{{ number_format($wordDiff) }})
        </span>
    </div>
@endif

@if ($showCompareButton)
    <button type="button"
            class="text-xs text-blue-600 dark:text-blue-400 hover:underline mt-1 inline-flex items-center"
            onclick="compareGameVersions('{{ $currentVersion->id }}', '{{ $game->latestVersion->id }}', '{{ $game->id }}')">
        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        Compare changes
    </button>
@endif
