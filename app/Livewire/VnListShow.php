<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\VnList;
use Illuminate\View\View;
use Livewire\Component;

class VnListShow extends Component
{
    public VnList $vnList;
    public bool $isOwner = false;
    public ?array $versionComparisonStats = null;

    protected $listeners = [
        'compare-game-versions' => 'compareGameVersions',
    ];

    public function mount(VnList $vnList, bool $isOwner): void
    {
        $this->vnList = $vnList;
        $this->isOwner = $isOwner;
    }

    public function render(): View
    {
        return view('livewire.vn-list-show');
    }

    public function compareGameVersions($params): void
    {
        $fromVersionId = $params['fromVersionId'] ?? null;
        $toVersionId = $params['toVersionId'] ?? null;
        $gameId = $params['gameId'] ?? null;

        if (! $fromVersionId || ! $toVersionId || ! $gameId) {
            return;
        }

        $game = Game::find($gameId);
        if (! $game) {
            return;
        }

        $fromVersion = GameVersion::find($fromVersionId);
        $toVersion = GameVersion::find($toVersionId);

        if (! $fromVersion || ! $toVersion) {
            return;
        }

        // Ensure fromVersion is the older one
        if ($fromVersion->published_at > $toVersion->published_at) {
            // Swap them
            $temp = $fromVersion;
            $fromVersion = $toVersion;
            $toVersion = $temp;
        }

        // Compare character stats
        $fromCharacterStats = $fromVersion->characterStats()
            ->where('iso_code', 'not like', 'q%')
            ->whereExists(function ($query) use ($fromVersion) {
                $query->selectRaw(1)
                    ->from('version_supported_languages')
                    ->where('game_version_id', $fromVersion->id)
                    ->whereColumn('version_supported_languages.iso_code', 'version_character_stats.iso_code')
                    ->where('is_available', true);
            })
            ->with(['character', 'language'])
            ->get();

        $toCharacterStats = $toVersion->characterStats()
            ->where('iso_code', 'not like', 'q%')
            ->whereExists(function ($query) use ($toVersion) {
                $query->selectRaw(1)
                    ->from('version_supported_languages')
                    ->where('game_version_id', $toVersion->id)
                    ->whereColumn('version_supported_languages.iso_code', 'version_character_stats.iso_code')
                    ->where('is_available', true); // Only include available languages
            })
            ->with(['character', 'language'])
            ->get();

        // Get unique languages that are available in either version
        $fromLanguages = $fromCharacterStats->pluck('language.id')->unique();
        $toLanguages = $toCharacterStats->pluck('language.id')->unique();
        $allLanguages = $fromLanguages->merge($toLanguages)->unique();

        $languages = [];
        foreach ($allLanguages as $langId) {
            $lang = null;

            // Find language details from either collection
            if ($fromCharacterStats->where('language.id', $langId)->first()) {
                $lang = $fromCharacterStats->where('language.id', $langId)->first()->language;
            } elseif ($toCharacterStats->where('language.id', $langId)->first()) {
                $lang = $toCharacterStats->where('language.id', $langId)->first()->language;
            }

            if ($lang) {
                $languages[] = [
                    'id' => $lang->id,
                    'name' => $lang->ref_name,
                    'flag' => $lang->flag_code,
                ];
            }
        }

        // Create word count matrices (character x language)
        $fromWordCounts = [];
        $toWordCounts = [];
        $allCharacters = [];

        // Process from version
        foreach ($fromCharacterStats as $stat) {
            $displayName = $stat->character->getDisplayName($game->source_language_id);
            $allCharacters[$displayName] = true;

            if (! isset($fromWordCounts[$displayName][$stat->language->id])) {
                $fromWordCounts[$displayName][$stat->language->id] = 0;
            }

            $fromWordCounts[$displayName][$stat->language->id] += $stat->words;
        }

        // Process to version
        foreach ($toCharacterStats as $stat) {
            $displayName = $stat->character->getDisplayName($game->source_language_id);
            $allCharacters[$displayName] = true;

            if (! isset($toWordCounts[$displayName][$stat->language->id])) {
                $toWordCounts[$displayName][$stat->language->id] = 0;
            }

            $toWordCounts[$displayName][$stat->language->id] += $stat->words;
        }

        // Calculate differences
        $characterDiffs = [];
        $languageTotals = [
            'from' => [],
            'to' => [],
            'diff' => [],
        ];

        foreach (array_keys($allCharacters) as $character) {
            $characterDiffs[$character] = [];

            foreach ($languages as $lang) {
                $fromCount = $fromWordCounts[$character][$lang['id']] ?? 0;
                $toCount = $toWordCounts[$character][$lang['id']] ?? 0;
                $diff = $toCount - $fromCount;

                $characterDiffs[$character][$lang['id']] = [
                    'from' => $fromCount,
                    'to' => $toCount,
                    'diff' => $diff,
                ];

                // Update language totals
                if (! isset($languageTotals['from'][$lang['id']])) {
                    $languageTotals['from'][$lang['id']] = 0;
                }
                if (! isset($languageTotals['to'][$lang['id']])) {
                    $languageTotals['to'][$lang['id']] = 0;
                }
                if (! isset($languageTotals['diff'][$lang['id']])) {
                    $languageTotals['diff'][$lang['id']] = 0;
                }

                $languageTotals['from'][$lang['id']] += $fromCount;
                $languageTotals['to'][$lang['id']] += $toCount;
                $languageTotals['diff'][$lang['id']] += $diff;
            }
        }

        // Sort characters
        $sortedCharacters = array_keys($allCharacters);
        sort($sortedCharacters, SORT_NATURAL | SORT_FLAG_CASE);

        // Compare file stats
        $fromFileCategories = $fromVersion->fileCategories()->with('fileTypes')->get();
        $toFileCategories = $toVersion->fileCategories()->with('fileTypes')->get();

        $fileCategoryComparisons = [];

        // Get unique categories
        $allCategories = $fromFileCategories->pluck('category')
            ->merge($toFileCategories->pluck('category'))
            ->unique();

        foreach ($allCategories as $category) {
            $fromCategory = $fromFileCategories->firstWhere('category', $category);
            $toCategory = $toFileCategories->firstWhere('category', $category);

            $categoryComparison = [
                'category' => $category,
                'from' => [
                    'count' => $fromCategory ? $fromCategory->total_count : 0,
                    'size' => $fromCategory ? $fromCategory->total_size : 0,
                ],
                'to' => [
                    'count' => $toCategory ? $toCategory->total_count : 0,
                    'size' => $toCategory ? $toCategory->total_size : 0,
                ],
                'diff' => [
                    'count' => ($toCategory ? $toCategory->total_count : 0) - ($fromCategory ? $fromCategory->total_count : 0),
                    'size' => ($toCategory ? $toCategory->total_size : 0) - ($fromCategory ? $fromCategory->total_size : 0),
                ],
                'fileTypes' => [],
            ];

            // Get all unique file types within this category
            $fromFileTypes = $fromCategory ? $fromCategory->fileTypes->pluck('extension') : collect();
            $toFileTypes = $toCategory ? $toCategory->fileTypes->pluck('extension') : collect();
            $allFileTypes = $fromFileTypes->merge($toFileTypes)->unique();

            foreach ($allFileTypes as $extension) {
                $fromFileType = $fromCategory ? $fromCategory->fileTypes->firstWhere('extension', $extension) : null;
                $toFileType = $toCategory ? $toCategory->fileTypes->firstWhere('extension', $extension) : null;

                $categoryComparison['fileTypes'][$extension] = [
                    'from' => [
                        'count' => $fromFileType ? $fromFileType->count : 0,
                        'size' => $fromFileType ? $fromFileType->size : 0,
                    ],
                    'to' => [
                        'count' => $toFileType ? $toFileType->count : 0,
                        'size' => $toFileType ? $toFileType->size : 0,
                    ],
                    'diff' => [
                        'count' => ($toFileType ? $toFileType->count : 0) - ($fromFileType ? $fromFileType->count : 0),
                        'size' => ($toFileType ? $toFileType->size : 0) - ($fromFileType ? $fromFileType->size : 0),
                    ],
                ];
            }

            $fileCategoryComparisons[] = $categoryComparison;
        }

        $this->versionComparisonStats = [
            'fromVersion' => $fromVersion,
            'toVersion' => $toVersion,
            'characters' => $sortedCharacters,
            'languages' => $languages,
            'characterDiffs' => $characterDiffs,
            'languageTotals' => $languageTotals,
            'fileCategories' => $fileCategoryComparisons,
        ];

        $this->dispatch('open-dialog', dialogId: 'version-comparison');
    }
}
