<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Character;
use App\Models\DialogueLine;
use App\Models\Game;
use App\Models\GameVersion;
use App\Models\Language;
use App\Services\DialogueSearchService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class DialogueBrowser extends Component
{
    use WithPagination;

    // Optional game and version IDs
    public ?int $gameId = null;
    public ?int $versionId = null;

    // Search and filter controls
    public string $searchQuery = '';
    public ?string $selectedCharacter = null;
    public string $selectedLanguage = 'eng';
    public ?string $selectedContext = null;
    public bool $showSearchResults = false;
    public bool $showDuplicates = false;
    public bool $groupByContext = true;
    public int $minLineLength = 10;
    public int $minDuplicateCount = 3;
    public int $maxDuplicates = 10;

    // Pagination
    public int $perPage = 20;

    // We don't need to include the page in queryString since that's handled by WithPagination
    protected $queryString = [
        'searchQuery' => ['except' => ''],
        'selectedCharacter' => ['except' => null],
        'selectedLanguage' => ['except' => 'eng'],
        'selectedContext' => ['except' => null],
        'groupByContext' => ['except' => true],
        'showDuplicates' => ['except' => false],
        'minLineLength' => ['except' => 10],
        'minDuplicateCount' => ['except' => 3],
        'maxDuplicates' => ['except' => 10],
        'perPage' => ['except' => 20],
        'gameId' => ['except' => null],
        'versionId' => ['except' => null],
    ];

    protected $listeners = ['gameSelected', 'versionSelected'];

    public function mount(?int $gameId = null, ?int $versionId = null): void
    {
        $this->gameId = $gameId;
        $this->versionId = $versionId;

        // Set showSearchResults based on whether searchQuery is not empty
        // This ensures the search works on page load with query parameters
        if (! empty($this->searchQuery)) {
            $this->showSearchResults = true;
        }
    }

    public function gameSelected(int $gameId): void
    {
        $this->gameId = $gameId;
        $this->versionId = null;
        $this->selectedCharacter = null;
        $this->selectedContext = null;
        $this->resetPage();
    }

    public function versionSelected(int $versionId): void
    {
        $this->versionId = $versionId;
        $this->selectedCharacter = null;
        $this->selectedContext = null;
        $this->resetPage();
    }

    public function updated($name, $value): void
    {
        // Handle specific property updates
        match ($name) {
            'maxDuplicates' => $this->handleMaxDuplicatesUpdate($value),
            'minLineLength' => $this->handleMinLineLengthUpdate($value),
            'minDuplicateCount' => $this->handleMinDuplicateCountUpdate($value),
            default => null,
        };

        // Reset page for these specific updates
        if (in_array($name, ['maxDuplicates', 'minLineLength', 'minDuplicateCount'])) {
            $this->resetPage();
        }
    }

    public function updatedSearchQuery(): void
    {
        $this->showSearchResults = ! empty($this->searchQuery);
        $this->showDuplicates = false;
        $this->resetPage();
    }

    public function updatedSelectedCharacter(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedLanguage(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedContext(): void
    {
        $this->resetPage();
    }

    public function updatedGroupByContext(): void
    {
        $this->resetPage();
    }

    public function updatedShowDuplicates(): void
    {
        if ($this->showDuplicates) {
            $this->showSearchResults = false;
            $this->searchQuery = '';
        }
        $this->resetPage();
    }

    public function toggleDuplicates(): void
    {
        $this->showDuplicates = ! $this->showDuplicates;
        if ($this->showDuplicates) {
            $this->showSearchResults = false;
            $this->searchQuery = '';
        }
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->searchQuery = '';
        $this->showSearchResults = false;
        $this->resetPage();
    }

    public function gotoPage($page): void
    {
        $this->setPage($page);
    }

    public function render(): View
    {
        // Get available games that have dialogue data
        $games = Game::where('is_visible', true)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('game_versions')
                    ->join('version_dialogue_lines', 'game_versions.id', '=', 'version_dialogue_lines.game_version_id')
                    ->whereColumn('game_versions.game_id', 'games.id');
            })
            ->orderBy('name')
            ->get();

        $versions = collect([]);
        $characters = collect([]);
        $contexts = collect([]);
        $statistics = null;
        $topDuplicates = collect([]);

        if ($this->gameId) {
            // Get versions that have dialogue data
            $versions = GameVersion::where('game_id', $this->gameId)
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('version_dialogue_lines')
                        ->whereColumn('version_dialogue_lines.game_version_id', 'game_versions.id');
                })
                ->orderByDesc('published_at')
                ->get();

            if ($this->versionId) {
                // Get character and context options for the selected version
                $characters = Character::join('version_dialogue_lines', 'characters.id', '=',
                    'version_dialogue_lines.character_id')
                    ->where('version_dialogue_lines.game_version_id', $this->versionId)
                    ->where('version_dialogue_lines.iso_code', $this->selectedLanguage)
                    ->distinct('characters.character_id')
                    ->select('characters.id', 'characters.character_id', 'characters.display_names')
                    ->get()
                    ->map(function ($character) {
                        // Get display name for the selected language, or fallback to another language
                        $displayName = $character->getDisplayName($this->selectedLanguage) ??
                            $character->getDisplayName('eng') ??
                            $character->character_id;
                        $character->display_name = $displayName;

                        return $character;
                    })
                    ->sortBy('display_name');

                $contexts = DialogueLine::where('game_version_id', $this->versionId)
                    ->where('iso_code', $this->selectedLanguage)
                    ->whereNotNull('context')
                    ->distinct('context')
                    ->pluck('context');

                // Get statistics for the selected version
                $searchService = app(DialogueSearchService::class);
                $statistics = $searchService->getVersionStatistics(GameVersion::find($this->versionId));
            }
        }

        // Get available languages
        $languages = Language::whereExists(function ($query) {
            $query->select('version_dialogue_lines.id')
                ->from('version_dialogue_lines')
                ->whereColumn('version_dialogue_lines.iso_code', 'iso_639_3_languages.id')
                ->limit(1);
        })
            ->orderBy('ref_name')
            ->get();

        // Prepare search results or top duplicates based on view mode
        $searchResults = new LengthAwarePaginator([], 0, $this->perPage);

        $searchService = app(DialogueSearchService::class);

        if ($this->showDuplicates) {
            $filters = [
                'language' => $this->selectedLanguage,
            ];

            if ($this->minLineLength ?? false) {
                $filters['min_length'] = $this->minLineLength;
            }

            if ($this->minDuplicateCount ?? false) {
                $filters['min_count'] = $this->minDuplicateCount;
            }

            if ($this->gameId) {
                $filters['game_id'] = $this->gameId;
            }

            if ($this->versionId) {
                $filters['version_id'] = $this->versionId;
            }

            if ($this->selectedCharacter) {
                $filters['character_id'] = $this->selectedCharacter;
            }

            $topDuplicates = $searchService->getTopDuplicates($filters, $this->maxDuplicates ?? 10);
        } elseif ($this->showSearchResults && ! empty($this->searchQuery)) {
            $filters = [
                'language' => $this->selectedLanguage,
            ];

            if ($this->gameId) {
                $filters['game_id'] = $this->gameId;
            }

            if ($this->versionId) {
                $filters['version_id'] = $this->versionId;
            }

            if ($this->selectedCharacter) {
                $filters['character_id'] = $this->selectedCharacter;
            }

            if ($this->selectedContext) {
                $filters['context'] = $this->selectedContext;
            }

            $searchResults = $searchService->search(
                $this->searchQuery,
                $filters,
                $this->perPage,
                $this->paginators['page'] ?? 1
            );
        }

        // Group results by context if requested
        $groupedResults = collect([]);

        if ($this->groupByContext && $searchResults->count() > 0) {
            $groupedResults = $searchResults->groupBy('context');
        }

        return view('dialogue.browser.index', [
            'games' => $games,
            'versions' => $versions,
            'characters' => $characters,
            'languages' => $languages,
            'contexts' => $contexts,
            'searchResults' => $searchResults,
            'groupedResults' => $groupedResults,
            'statistics' => $statistics,
            'topDuplicates' => $topDuplicates,
        ])
            ->layout('components.layouts.app', [
                'metaTags' => [
                    'title' => $this->gameId
                        ? 'Dialogue Browser for ' . Game::where('id',
                            $this->gameId)->first('name')->name . ' - ' . config('app.name')
                        : 'Dialogue Browser - ' . config('app.name'),
                ],
                'noindex' => true,
            ]);
    }

    private function handleMaxDuplicatesUpdate($value): void
    {
        // Only update if a valid number is provided
        $numValue = is_numeric($value) ? intval($value) : null;
        if ($numValue > 0) {
            $this->maxDuplicates = min(max(5, $numValue), 50);
        }
    }

    private function handleMinLineLengthUpdate($value): void
    {
        // Only update if a valid number is provided
        $numValue = is_numeric($value) ? intval($value) : null;
        if ($numValue > 0) {
            $this->minLineLength = min(max(3, $numValue), 50);
        }
    }

    private function handleMinDuplicateCountUpdate($value): void
    {
        // Only update if a valid number is provided
        $numValue = is_numeric($value) ? intval($value) : null;
        if ($numValue > 0) {
            $this->minDuplicateCount = min(max(2, $numValue), 20);
        }
    }
}
