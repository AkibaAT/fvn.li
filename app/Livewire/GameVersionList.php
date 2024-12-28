<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\GameVersion;
use App\Models\Language;
use App\Traits\HasSocialMetaTags;
use App\Traits\HasSortableColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class GameVersionList extends Component
{
    use HasSocialMetaTags, HasSortableColumns, WithPagination;

    protected const array AVAILABLE_SORT_FIELDS = [
        'published_at' => 'Release Date',
        'stats_words' => 'Word Count',
        'rating' => 'Rating',
        'rating_count' => 'Review Count',
    ];

    public string $search = '';
    public array $selectedPlatforms = [];
    public array $selectedLanguages = [];
    public ?string $gameId = null;
    public string $sortField = 'published_at';
    public string $sortDirection = 'desc';
    public string|int $perPage = 12;
    public int $page = 1;

    protected array $validPerPageValues = [9, 18, 27];

    protected $queryString = [
        'search' => ['except' => ''],
        'selectedPlatforms' => ['except' => []],
        'selectedLanguages' => ['except' => []],
        'gameId' => ['except' => null],
        'sortField' => ['except' => 'published_at'],
        'sortDirection' => ['except' => 'desc'],
        'perPage' => ['except' => 9],
        'page' => ['except' => 1],
    ];

    private LengthAwarePaginator $versions;

    public function mount(): void
    {
        $this->normalizePerPage();
    }

    public function updated($name): void
    {
        if ($name === 'perPage') {
            $this->normalizePerPage();
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function toggleFilter(string $type, string $value): void
    {
        $property = match ($type) {
            'platform' => 'selectedPlatforms',
            'language' => 'selectedLanguages',
            default => null,
        };

        if ($property === null) {
            return;
        }

        $decodedValue = $this->decodeFilterValue($value);
        $array = array_map([$this, 'decodeFilterValue'], $this->{$property});

        if (in_array($decodedValue, $array)) {
            $array = array_diff($array, [$decodedValue]);
        } else {
            $array[] = $decodedValue;
        }

        $this->{$property} = array_map([$this, 'encodeFilterValue'], array_values($array));
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->selectedPlatforms = [];
        $this->selectedLanguages = [];
        $this->sortField = 'published_at';
        $this->sortDirection = 'desc';
        $this->resetPage();
    }

    public function sortBy($field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function render(): View
    {
        $query = GameVersion::query()
            ->with(['game', 'languageStats.language'])
            ->when($this->gameId, fn ($q) => $q->where('game_id', $this->gameId))
            ->when($this->search, function ($q) {
                $q->whereHas('game', function (Builder $query) {
                    $query->where('name', 'ilike', "%{$this->search}%");
                });
            })
            ->when(! empty($this->selectedPlatforms), function ($q) {
                foreach ($this->selectedPlatforms as $platform) {
                    $decodedPlatform = $this->decodeFilterValue($platform);
                    $q->where("platform_{$decodedPlatform}", true);
                }
            })
            ->when(! empty($this->selectedLanguages), function ($q) {
                $decodedLanguages = array_map([$this, 'decodeFilterValue'], $this->selectedLanguages);
                $q->whereHas('languageStats', function ($query) use ($decodedLanguages) {
                    $query->whereIn('iso_code', $decodedLanguages);
                });
            });

        // Handle sorting
        switch ($this->sortField) {
            case 'stats_words':
                // Sort by word count of default game language
                $query->orderByRaw('(
                    SELECT words
                    FROM version_language_stats
                    WHERE version_language_stats.game_version_id = game_versions.id
                    AND version_language_stats.iso_code = (
                        SELECT default_language_code
                        FROM games
                        WHERE games.id = game_versions.game_id
                    )
                ) ' . $this->sortDirection . ' NULLS LAST');
                break;
            case 'rating':
            case 'rating_count':
                $query->orderByRaw("{$this->sortField} {$this->sortDirection} NULLS LAST");
                break;
            default:
                $query->orderBy($this->sortField, $this->sortDirection);
        }

        $this->versions = $query->paginate($this->perPage);
        $metaTags = $this->getMetaTags();
        app('view')->share('metaTags', $metaTags);
        $this->updateMeta($metaTags);

        return view('livewire.game-version-list', [
            'versions' => $this->versions,
            'metaTags' => $metaTags,
            ...$this->getFilterOptions(),
        ]);
    }

    protected function normalizePerPage(): void
    {
        $intValue = filter_var($this->perPage, FILTER_VALIDATE_INT);

        if ($intValue === false || ! in_array($intValue, $this->validPerPageValues)) {
            $this->perPage = $this->validPerPageValues[0];

            return;
        }

        $this->perPage = $intValue;
    }

    protected function encodeFilterValue(string $value): string
    {
        return rawurlencode($value);
    }

    protected function decodeFilterValue(string $value): string
    {
        return rawurldecode($value);
    }

    protected function getFilterOptions(): array
    {
        $baseQuery = GameVersion::query()
            ->when($this->gameId, fn ($q) => $q->where('game_id', $this->gameId));

        $languageQuery = Language::query()
            ->whereExists(function ($query) use ($baseQuery) {
                $query->select('id')
                    ->from('version_language_stats')
                    ->whereIn('game_version_id', $baseQuery->clone()->select('id'));
            });

        return [
            'platforms' => [
                'windows' => 'Windows',
                'linux' => 'Linux',
                'mac' => 'Mac',
                'android' => 'Android',
                'web' => 'Web',
            ],
            'languages' => $languageQuery
                ->orderBy('ref_name')
                ->get()
                ->mapWithKeys(fn ($lang) => [$this->encodeFilterValue($lang->id) => $lang->ref_name])
                ->all(),
        ];
    }

    protected function updateMeta(array $metaTags): void
    {
        if (method_exists($this, 'dispatch')) {
            $this->dispatch('updateMetaTags', metaTags: $metaTags);
        }
    }
}
