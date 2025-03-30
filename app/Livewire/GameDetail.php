<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Character;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\Rating;
use App\Models\VnList;
use App\Traits\HasSocialMetaTags;
use App\Traits\SortsVnLists;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class GameDetail extends Component
{
    use HasSocialMetaTags, SortsVnLists, WithPagination;

    public Game $game;

    public bool $showAllRatings = false;

    public int $reviewsPage = 1;

    public int $versionsPage = 1;

    public ?int $selectedRating = null;

    public string|int $versionsPerPage = 5;

    public string|int $reviewsPerPage = 5;

    public ?array $characterStats = null;

    public ?int $selectedVersionId = null;

    public ?GameVersion $selectedVersion = null;

    public ?int $compareFromVersionId = null;

    public ?int $compareToVersionId = null;

    public ?array $versionComparisonStats = null;

    protected array $validPerPageValues = [5, 10, 25];

    protected $queryString = [
        'showAllRatings' => ['except' => false],
        'reviewsPage' => ['except' => 1],
        'versionsPage' => ['except' => 1],
        'versionsPerPage' => ['except' => 5],
        'reviewsPerPage' => ['except' => 5],
        'selectedRating' => ['except' => null],
    ];

    public function mount(Game $game): void
    {
        $this->game = $game;
        $this->normalizePerPage('versionsPerPage');
        $this->normalizePerPage('reviewsPerPage');
    }

    public function updated($name): void
    {
        if (in_array($name, ['versionsPerPage', 'reviewsPerPage'])) {
            $this->normalizePerPage($name);
        }
    }

    public function toggleRatingsView(): void
    {
        $this->showAllRatings = ! $this->showAllRatings;
        $this->selectedRating = null;
        $this->reviewsPage = 1;
        $this->setPage(1, 'reviewsPage');
    }

    public function updatedSelectedRating(): void
    {
        $this->reviewsPage = 1;
        $this->setPage(1, 'reviewsPage');
    }

    public function render(): View
    {
        // Eager load all the relationships we need
        $this->game->load([
            'latestVersion.languageStats.language',
            'latestVersion.supportedLanguages.language',
        ]);

        $reviews = $this->game->ratings()
            ->where('is_visible', true)
            ->when(! $this->showAllRatings, fn ($query) => $query->where('is_reviewed', true))
            ->when($this->selectedRating !== null, fn ($query) => $query->where('rating', $this->selectedRating))
            ->with('rater')
            ->orderByDesc('published_at')
            ->paginate($this->reviewsPerPage, pageName: 'reviewsPage');

        $availableRatings = $this->game->ratings()
            ->where('is_visible', true)
            ->when(! $this->showAllRatings, fn ($query) => $query->where('is_reviewed', true))
            ->distinct()
            ->pluck('rating')
            ->sort()
            ->values();

        // Paginate game versions
        $versions = $this->game->gameVersions()
            ->with([
                'supportedLanguages.language',
                'languageStats.language',
            ])
            ->paginate($this->versionsPerPage, pageName: 'versionsPage');

        $latestVersion = $this->game->latestVersion;
        $supportedLanguages = $latestVersion?->supportedLanguages
            ->where('is_available', true)
            ->map(fn ($sl) => [
                'iso_code' => $sl->iso_code,
                'ref_name' => $sl->language->ref_name,
                'flag_code' => $sl->language->flag_code,
            ])
            ?? collect();

        $versionCharacterCounts = [];
        if ($latestVersion) {
            $versionCharacterCounts[$latestVersion->id] = Character::countUniqueCharactersInLanguage(
                $this->game->id,
                $this->game->source_language_id,
                $latestVersion->id
            );
        }
        foreach ($versions as $version) {
            if ($version->id === $latestVersion->id) {
                continue;
            }
            $versionCharacterCounts[$version->id] = Character::countUniqueCharactersInLanguage(
                $this->game->id,
                $this->game->source_language_id,
                $version->id
            );
        }

        // Get user lists containing this game
        $userLists = null;
        if (Auth::check()) {
            $userLists = VnList::with(['entries' => function ($query) {
                $query->where('game_id', $this->game->id);
            }])
                ->where('user_id', Auth::id())
                ->whereHas('entries', function ($query) {
                    $query->where('game_id', $this->game->id);
                })
                ->get();

            // Apply the custom list ordering
            if ($userLists->isNotEmpty()) {
                $userLists = $this->sortListsByType($userLists);
            }
        }

        // Get public lists containing this game (excluding user's own lists)
        $publicLists = null;
        $publicLists = VnList::with(['entries' => function ($query) {
            $query->where('game_id', $this->game->id);
        }, 'user'])
            ->where('is_public', true)
            ->whereHas('entries', function ($query) {
                $query->where('game_id', $this->game->id);
            })
            ->when(Auth::check(), function ($query) {
                $query->where('user_id', '!=', Auth::id());
            })
            ->limit(5)
            ->latest()
            ->get();

        // Apply the custom list ordering
        if ($publicLists->isNotEmpty()) {
            $publicLists = $this->sortListsByType($publicLists);
        }

        $metaTags = $this->getMetaTags();
        app('view')->share('metaTags', $metaTags);
        $this->updateMeta($metaTags);

        // Pass the latestVersion data explicitly to make it clear we're using version data
        $latestVersion = $this->game->latestVersion;

        return view('games.detail.show', [
            'reviews' => $reviews,
            'versions' => $versions,
            'latestVersion' => $latestVersion,
            'platforms' => [
                'windows' => $latestVersion?->is_windows ?? false,
                'linux' => $latestVersion?->is_linux ?? false,
                'mac' => $latestVersion?->is_mac ?? false,
                'android' => $latestVersion?->is_android ?? false,
                'web' => $latestVersion?->is_web ?? false,
            ],
            'rating' => $latestVersion?->rating,
            'ratingCount' => $latestVersion?->rating_count,
            'devlog' => $latestVersion?->devlog,
            'englishStats' => $latestVersion?->getStatsForLanguage('eng'),
            'supportedLanguages' => $supportedLanguages,
            'metaTags' => $this->getMetaTags(),
            'availableRatings' => $availableRatings,
            'versionCharacterCounts' => $versionCharacterCounts,
            'userLists' => $userLists,
            'publicLists' => $publicLists,
        ])
            ->layout('components.layouts.app', [
                'metaTags' => $metaTags,
                'noindex' => ! $this->game->is_visible,
            ]);
    }

    public function getMetaTags(): array
    {
        // Get latest version
        $latestVersion = $this->game->latestVersion;

        // Prepare basic game info for description
        $descriptionParts = [];

        if ($this->game->is_visible) {
            if ($this->game->status) {
                $descriptionParts[] = "A {$this->game->status} game";
            }

            if ($this->game->game_engine && $this->game->game_engine !== 'unknown') {
                $descriptionParts[] = "made with {$this->game->game_engine}";
            }

            // Add platforms from latest version
            $platforms = [];
            if ($latestVersion) {
                if ($latestVersion->is_windows) {
                    $platforms[] = 'Windows';
                }
                if ($latestVersion->is_linux) {
                    $platforms[] = 'Linux';
                }
                if ($latestVersion->is_mac) {
                    $platforms[] = 'Mac';
                }
                if ($latestVersion->is_android) {
                    $platforms[] = 'Android';
                }
                if ($latestVersion->is_web) {
                    $platforms[] = 'Web';
                }
            }
            if (! empty($platforms)) {
                $descriptionParts[] = 'available on ' . implode(', ', $platforms);
            }

            // Add word count from latest version if available
            $englishWordCount = $this->game->getEnglishWordCount();
            if ($englishWordCount) {
                $descriptionParts[] = number_format($englishWordCount) . ' words long';
            }

            // Add rating from latest version if available
            if ($latestVersion?->rating_count) {
                $descriptionParts[] = 'rated ' . number_format($latestVersion->rating_count) . ' times';
            }
        } else {
            // Get rating count from ratings table
            $ratingCount = Rating::where('game_id', $this->game->id)->where('is_visible', true)->count();

            $descriptionParts[] = 'An unlisted game rated ' . number_format($ratingCount) . ' times';
        }

        // Truncate description to around 160 characters
        $description = implode(', ', $descriptionParts) . '.';
        $description = substr($description, 0, 160);

        return [
            'title' => $this->game->name . ' - ' . config('app.name'),
            'description' => $description,
            'image' => $this->game->thumb_url ?: asset('favicon.ico'),
        ];
    }

    public function showCharacterStats(int $versionId): void
    {
        $this->selectedVersionId = $versionId;

        // Get all character stats with relationships
        $characterStats = GameVersion::find($versionId)
            ->characterStats()
            ->where('iso_code', 'not like', 'q%')
            ->whereExists(function ($query) use ($versionId) {
                $query->selectRaw(1)
                    ->from('version_supported_languages')
                    ->where('game_version_id', $versionId)
                    ->whereColumn('version_supported_languages.iso_code', 'version_character_stats.iso_code')
                    ->where('is_available', true);
            })
            ->with(['character', 'language'])
            ->get();

        // Get unique languages
        $languages = $characterStats
            ->sortBy('language.ref_name')
            ->unique('language.id')
            ->values()
            ->map(fn ($stat) => [
                'id' => $stat->language->id,
                'name' => $stat->language->ref_name,
                'flag' => $stat->language->flag_code,
            ]);

        // Create word count matrix (character x language)
        $characters = [];
        $wordCounts = [];
        foreach ($characterStats as $stat) {
            $displayName = $stat->character->getDisplayName($this->game->source_language_id);
            $characters[$displayName] = $displayName;
            if (! isset($wordCounts[$displayName][$stat->language->id])) {
                $wordCounts[$displayName][$stat->language->id] = 0;
            }
            $wordCounts[$displayName][$stat->language->id] += $stat->words;
        }
        sort($characters, SORT_NATURAL | SORT_FLAG_CASE);

        // Calculate totals per language
        $languageTotals = [];
        foreach ($characterStats as $stat) {
            if (! isset($languageTotals[$stat->language->id])) {
                $languageTotals[$stat->language->id] = 0;
            }
            $languageTotals[$stat->language->id] += $stat->words;
        }

        $this->characterStats = [
            'characters' => $characters,
            'languages' => $languages,
            'wordCounts' => $wordCounts,
            'languageTotals' => $languageTotals,
        ];

        $this->dispatch('open-dialog', dialogId: "character-stats-{$versionId}");
    }

    public function showFileStats(int $versionId): void
    {
        $this->selectedVersionId = $versionId;

        $this->selectedVersion = $this->game->gameVersions()
            ->with([
                'fileCategories.fileTypes' => function ($query) {
                    $query->orderBy('extension');
                },
            ])
            ->find($versionId);

        $this->dispatch('open-dialog', dialogId: 'file-stats');
    }

    public function compareVersions(): void
    {
        if (! $this->compareFromVersionId || ! $this->compareToVersionId) {
            return;
        }

        $fromVersion = GameVersion::find($this->compareFromVersionId);
        $toVersion = GameVersion::find($this->compareToVersionId);

        if (! $fromVersion || ! $toVersion) {
            return;
        }

        // Ensure fromVersion is the older one
        if ($fromVersion->published_at > $toVersion->published_at) {
            // Swap them
            $temp = $fromVersion;
            $fromVersion = $toVersion;
            $toVersion = $temp;

            $this->compareFromVersionId = $fromVersion->id;
            $this->compareToVersionId = $toVersion->id;
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
            $displayName = $stat->character->getDisplayName($this->game->source_language_id);
            $allCharacters[$displayName] = true;

            if (! isset($fromWordCounts[$displayName][$stat->language->id])) {
                $fromWordCounts[$displayName][$stat->language->id] = 0;
            }

            $fromWordCounts[$displayName][$stat->language->id] += $stat->words;
        }

        // Process to version
        foreach ($toCharacterStats as $stat) {
            $displayName = $stat->character->getDisplayName($this->game->source_language_id);
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

    protected function normalizePerPage(string $property): void
    {
        $intValue = filter_var($this->{$property}, FILTER_VALIDATE_INT);

        if ($intValue === false || ! in_array($intValue, $this->validPerPageValues)) {
            $this->{$property} = $this->validPerPageValues[0];

            return;
        }

        $this->{$property} = $intValue;
    }

    protected function updateMeta(array $metaTags): void
    {
        if (method_exists($this, 'dispatch')) {
            $this->dispatch('updateMetaTags', metaTags: $metaTags);
        }
    }

    protected function encodeFilterValue(string $value): string
    {
        return rawurlencode($value);
    }

    protected function decodeFilterValue(string $value): string
    {
        return rawurldecode($value);
    }
}
