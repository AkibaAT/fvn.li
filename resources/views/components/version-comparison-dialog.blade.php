@php use App\Services\HelperService; @endphp
<dialog
    wire:ignore.self
    id="version-comparison"
    class="m-auto rounded-lg bg-gray-800 p-6 shadow-xl min-w-80 max-w-6xl text-gray-100 backdrop:backdrop-blur-md"
>
    <x-dialog-header title="Version Comparison"/>

    @if ($versionComparisonStats)
        <div class="mb-4">
            <div class="flex flex-col md:flex-row gap-4 items-center justify-between bg-gray-700/50 p-4 rounded-lg">
                <div>
                    <h3 class="text-sm font-medium text-gray-400">Comparing</h3>
                    <div class="flex items-center gap-2 mt-1">
                        <div class="font-medium text-gray-100">
                            Version {{ $versionComparisonStats['fromVersion']->version }}
                            <span class="text-sm text-gray-400">({{ $versionComparisonStats['fromVersion']->published_at->format('M j, Y') }})</span>
                        </div>
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                        <div class="font-medium text-gray-100">
                            Version {{ $versionComparisonStats['toVersion']->version }}
                            <span class="text-sm text-gray-400">({{ $versionComparisonStats['toVersion']->published_at->format('M j, Y') }})</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mb-8" x-data="{ activeTab: 'character' }">
            <ul class="flex border-b border-gray-700 text-sm" role="tablist">
                <li class="mr-1">
                    <button
                        class="py-2 px-4 border-b-2 focus:outline-none"
                        :class="activeTab === 'character' ? 'border-blue-400 text-blue-400' : 'border-transparent text-gray-400 hover:text-gray-100 hover:border-gray-600'"
                        role="tab"
                        id="character-tab"
                        aria-selected="true"
                        aria-controls="character-panel"
                        @click="activeTab = 'character'"
                    >
                        Character Stats
                    </button>
                </li>
                <li class="mr-1">
                    <button
                        class="py-2 px-4 border-b-2 focus:outline-none"
                        :class="activeTab === 'file' ? 'border-blue-400 text-blue-400' : 'border-transparent text-gray-400 hover:text-gray-100 hover:border-gray-600'"
                        role="tab"
                        id="file-tab"
                        aria-selected="false"
                        aria-controls="file-panel"
                        @click="activeTab = 'file'"
                    >
                        File Stats
                    </button>
                </li>
            </ul>
            
            <!-- Character Stats Tab -->
            <div class="pt-4" x-show="activeTab === 'character'" role="tabpanel" id="character-panel" aria-labelledby="character-tab">
                <div class="overflow-x-auto max-w-[calc(100vw-3rem)] -mx-6 px-6">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-700">
                                <th class="text-left py-2 px-3 font-medium">Character</th>
                                @foreach ($versionComparisonStats['languages'] as $lang)
                                    <th class="text-right py-2 px-3 font-medium" colspan="3">
                                        <div class="flex items-center justify-end gap-2">
                                            <span class="fi fi-{{ $lang['flag'] }} rounded-xs"></span>
                                            <span>{{ $lang['name'] }}</span>
                                        </div>
                                    </th>
                                @endforeach
                            </tr>
                            <tr class="border-b border-gray-700 text-xs text-gray-400">
                                <th class="text-left py-2 px-3"></th>
                                @foreach ($versionComparisonStats['languages'] as $lang)
                                    <th class="text-right py-2 px-1">Old</th>
                                    <th class="text-right py-2 px-1">New</th>
                                    <th class="text-right py-2 px-1">Diff</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            @foreach ($versionComparisonStats['characters'] as $character)
                                <tr class="hover:bg-gray-700/50">
                                    <td class="py-2 px-3">{{ $character }}</td>
                                    @foreach ($versionComparisonStats['languages'] as $lang)
                                        @php
                                            $stats = $versionComparisonStats['characterDiffs'][$character][$lang['id']] ?? null;
                                            $fromCount = $stats ? $stats['from'] : 0;
                                            $toCount = $stats ? $stats['to'] : 0;
                                            $diff = $stats ? $stats['diff'] : 0;
                                        @endphp
                                        <td class="py-2 px-1 text-right tabular-nums text-gray-400">
                                            {{ $fromCount ? number_format($fromCount) : '-' }}
                                        </td>
                                        <td class="py-2 px-1 text-right tabular-nums">
                                            {{ $toCount ? number_format($toCount) : '-' }}
                                        </td>
                                        <td class="py-2 px-1 text-right tabular-nums {{ $diff > 0 ? 'text-green-400' : ($diff < 0 ? 'text-red-400' : 'text-gray-400') }}">
                                            @if ($diff != 0)
                                                {{ $diff > 0 ? '+' : '' }}{{ number_format($diff) }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-gray-700 font-medium">
                            <tr>
                                <td class="py-2 px-3">Total</td>
                                @foreach ($versionComparisonStats['languages'] as $lang)
                                    @php
                                        $fromTotal = $versionComparisonStats['languageTotals']['from'][$lang['id']] ?? 0;
                                        $toTotal = $versionComparisonStats['languageTotals']['to'][$lang['id']] ?? 0;
                                        $diffTotal = $versionComparisonStats['languageTotals']['diff'][$lang['id']] ?? 0;
                                    @endphp
                                    <td class="py-2 px-1 text-right tabular-nums text-gray-400">
                                        {{ $fromTotal ? number_format($fromTotal) : '-' }}
                                    </td>
                                    <td class="py-2 px-1 text-right tabular-nums">
                                        {{ $toTotal ? number_format($toTotal) : '-' }}
                                    </td>
                                    <td class="py-2 px-1 text-right tabular-nums {{ $diffTotal > 0 ? 'text-green-400' : ($diffTotal < 0 ? 'text-red-400' : 'text-gray-400') }}">
                                        @if ($diffTotal != 0)
                                            {{ $diffTotal > 0 ? '+' : '' }}{{ number_format($diffTotal) }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            <!-- File Stats Tab -->
            <div class="pt-4" x-show="activeTab === 'file'" role="tabpanel" id="file-panel" aria-labelledby="file-tab">
                <div class="space-y-6">
                    <!-- Summary -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-100 mb-4">File Summary</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            @foreach ($versionComparisonStats['fileCategories'] as $category)
                                <div class="bg-gray-700/50 p-4 rounded-lg">
                                    <div class="text-sm font-medium text-gray-400">
                                        {{ Str::title($category['category']) }}
                                    </div>
                                    <div class="mt-1 flex items-baseline">
                                        <div class="text-sm text-gray-400">
                                            {{ number_format($category['from']['count']) }}
                                        </div>
                                        <div class="mx-1 text-gray-500">→</div>
                                        <div class="text-base font-semibold text-gray-100">
                                            {{ number_format($category['to']['count']) }}
                                        </div>
                                        @if ($category['diff']['count'] != 0)
                                            <div class="ml-2 text-sm {{ $category['diff']['count'] > 0 ? 'text-green-400' : 'text-red-400' }}">
                                                {{ $category['diff']['count'] > 0 ? '+' : '' }}{{ number_format($category['diff']['count']) }}
                                            </div>
                                        @endif
                                    </div>
                                    <div class="mt-1 flex items-baseline text-sm">
                                        <div class="text-gray-400">
                                            {{ HelperService::formatBytes($category['from']['size']) }}
                                        </div>
                                        <div class="mx-1 text-gray-500">→</div>
                                        <div class="text-gray-100">
                                            {{ HelperService::formatBytes($category['to']['size']) }}
                                        </div>
                                        @if ($category['diff']['size'] != 0)
                                            <div class="ml-2 {{ $category['diff']['size'] > 0 ? 'text-green-400' : 'text-red-400' }}">
                                                {{ $category['diff']['size'] > 0 ? '+' : '' }}{{ HelperService::formatBytes(abs($category['diff']['size'])) }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    
                    <!-- Detailed Breakdown -->
                    <div class="space-y-6">
                        @foreach ($versionComparisonStats['fileCategories'] as $category)
                            @if (count($category['fileTypes']) > 0)
                                <div>
                                    <h4 class="text-base font-medium text-gray-100 mb-2">
                                        {{ Str::title($category['category']) }} Files
                                    </h4>
                                    <div class="bg-gray-700/50 rounded-lg overflow-hidden">
                                        <table class="min-w-full divide-y divide-gray-600">
                                            <thead>
                                                <tr>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                                        Type
                                                    </th>
                                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-400 uppercase tracking-wider" colspan="3">
                                                        Count
                                                    </th>
                                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-400 uppercase tracking-wider" colspan="3">
                                                        Size
                                                    </th>
                                                </tr>
                                                <tr class="border-b border-gray-700 text-xs text-gray-400">
                                                    <th class="px-4 py-1 text-left"></th>
                                                    <th class="px-2 py-1 text-right">Old</th>
                                                    <th class="px-2 py-1 text-right">New</th>
                                                    <th class="px-2 py-1 text-right">Diff</th>
                                                    <th class="px-2 py-1 text-right">Old</th>
                                                    <th class="px-2 py-1 text-right">New</th>
                                                    <th class="px-2 py-1 text-right">Diff</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-600">
                                                @foreach ($category['fileTypes'] as $extension => $typeStats)
                                                    <tr>
                                                        <td class="px-4 py-2 text-sm text-gray-100">
                                                            {{ $extension }}
                                                        </td>
                                                        <!-- Count -->
                                                        <td class="px-2 py-2 text-sm text-gray-400 text-right">
                                                            {{ number_format($typeStats['from']['count']) }}
                                                        </td>
                                                        <td class="px-2 py-2 text-sm text-gray-100 text-right">
                                                            {{ number_format($typeStats['to']['count']) }}
                                                        </td>
                                                        <td class="px-2 py-2 text-sm text-right {{ $typeStats['diff']['count'] > 0 ? 'text-green-400' : ($typeStats['diff']['count'] < 0 ? 'text-red-400' : 'text-gray-400') }}">
                                                            @if ($typeStats['diff']['count'] != 0)
                                                                {{ $typeStats['diff']['count'] > 0 ? '+' : '' }}{{ number_format($typeStats['diff']['count']) }}
                                                            @else
                                                                -
                                                            @endif
                                                        </td>
                                                        <!-- Size -->
                                                        <td class="px-2 py-2 text-sm text-gray-400 text-right">
                                                            {{ HelperService::formatBytes($typeStats['from']['size']) }}
                                                        </td>
                                                        <td class="px-2 py-2 text-sm text-gray-100 text-right">
                                                            {{ HelperService::formatBytes($typeStats['to']['size']) }}
                                                        </td>
                                                        <td class="px-2 py-2 text-sm text-right {{ $typeStats['diff']['size'] > 0 ? 'text-green-400' : ($typeStats['diff']['size'] < 0 ? 'text-red-400' : 'text-gray-400') }}">
                                                            @if ($typeStats['diff']['size'] != 0)
                                                                {{ $typeStats['diff']['size'] > 0 ? '+' : '' }}{{ HelperService::formatBytes(abs($typeStats['diff']['size'])) }}
                                                            @else
                                                                -
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="flex items-center justify-center p-4">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-100"></div>
        </div>
    @endif

    <x-dialog-footer/>
</dialog> 