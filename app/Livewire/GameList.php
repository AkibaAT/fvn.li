<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Game;
use App\Models\Language;
use App\Traits\HasSocialMetaTags;
use App\Traits\HasSortableColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class GameList extends Component
{
    use HasSocialMetaTags, HasSortableColumns, WithPagination;

    private static array $filterOptions = [];

    protected const array AVAILABLE_SORT_FIELDS = [
        'latest_version_published_at' => 'Latest Update',
        'initially_published_at' => 'Initial Release',
        'english_word_count' => 'Word Count',
        'rating_count' => 'Review Count',
        'name' => 'Name',
    ];

    public string $search = '';

    public array $selectedStatuses = [];

    public array $selectedEngines = [];

    public array $selectedPlatforms = [];

    public array $selectedLanguages = [];

    public bool $nsfw = false;

    public bool $sfw = false;

    public bool $showHidden = false;

    public string $sortField = 'latest_version_published_at';

    public string $sortDirection = 'desc';

    public string|int $perPage = 9;

    public int $page = 1;

    protected array $validPerPageValues = [9, 18, 27];

    protected $queryString = [
        'search' => ['except' => ''],
        'selectedStatuses' => ['except' => []],
        'selectedEngines' => ['except' => []],
        'selectedPlatforms' => ['except' => []],
        'selectedLanguages' => ['except' => []],
        'nsfw' => ['except' => false],
        'sfw' => ['except' => false],
        'sortField' => ['except' => 'latest_version_published_at'],
        'sortDirection' => ['except' => 'desc'],
        'perPage' => ['except' => 9],
        'page' => ['except' => 1],
        'showHidden' => ['except' => false],
    ];

    private LengthAwarePaginator $games;

    // Add a method to bust the cache when needed (e.g., when a new game is added)
    public static function clearFilterCache(): void
    {
        Cache::forget('game-filter-options');
        Cache::forget('game-languages');
        self::$filterOptions = [];
    }

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
            'status' => 'selectedStatuses',
            'engine' => 'selectedEngines',
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
        $this->selectedStatuses = [];
        $this->selectedEngines = [];
        $this->selectedPlatforms = [];
        $this->selectedLanguages = [];
        $this->nsfw = false;
        $this->sfw = false;
        $this->sortField = 'latest_version_published_at';
        $this->sortDirection = 'desc';
        $this->showHidden = false;
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
        $query = Game::query()
            ->select([
                'games.*',
                'latest_versions.published_at as latest_version_published_at',
                'latest_versions.id as latest_version_id',
                'latest_versions.rating as rating',
                'latest_versions.rating_count as rating_count',
                'latest_versions.devlog as devlog',
                'latest_versions.is_windows as is_windows',
                'latest_versions.is_linux as is_linux',
                'latest_versions.is_mac as is_mac',
                'latest_versions.is_android as is_android',
                'latest_versions.is_web as is_web',
                'english_stats.words as english_word_count',
                DB::raw('(
                    SELECT json_agg(json_build_object(
                        \'iso_code\', l.id,
                        \'ref_name\', l.ref_name,
                        \'flag_code\', l.flag_code
                    ) ORDER BY l.ref_name)
                    FROM version_supported_languages vsl
                    JOIN iso_639_3_languages l ON l.id = vsl.iso_code
                    WHERE vsl.game_version_id = latest_versions.id
                ) as supported_languages'),
            ])
            ->leftJoin('game_versions as latest_versions', function ($join) {
                $join->on('games.id', '=', 'latest_versions.game_id')
                    ->where('latest_versions.is_latest', true);
            })
            ->leftJoin('version_language_stats as english_stats', function ($join) {
                $join->on('latest_versions.id', '=', 'english_stats.game_version_id')
                    ->where('english_stats.iso_code', '=', 'eng');
            });

        // Apply filters
        $query->when(!$this->showHidden, fn ($q) => $q->where('is_visible', true))
            ->when($this->search, function ($q) {
                $q->where(function (Builder $query) {
                    $query->where('games.name', 'ilike', "%{$this->search}%")
                        ->orWhere('games.authors', 'ilike', "%{$this->search}%")
                        ->orWhere('games.tags', 'ilike', "%{$this->search}%")
                        ->orWhere('games.custom_tags', 'ilike', "%{$this->search}%");
                });
            })
            ->when(! empty($this->selectedStatuses), function ($q) {
                $decodedStatuses = array_map([$this, 'decodeFilterValue'], $this->selectedStatuses);
                $q->whereIn('games.status', $decodedStatuses);
            })
            ->when(! empty($this->selectedEngines), function ($q) {
                $decodedEngines = array_map([$this, 'decodeFilterValue'], $this->selectedEngines);
                $q->whereIn('games.game_engine', $decodedEngines);
            })
            ->when(! empty($this->selectedPlatforms), function ($q) {
                foreach ($this->selectedPlatforms as $platform) {
                    $decodedPlatform = $this->decodeFilterValue($platform);
                    $q->where("latest_versions.is_{$decodedPlatform}", true);
                }
            })
            ->when(! empty($this->selectedLanguages), function ($q) {
                $decodedLanguages = array_map([$this, 'decodeFilterValue'], $this->selectedLanguages);
                $q->whereExists(function ($query) use ($decodedLanguages) {
                    $query->select(DB::raw(1))
                        ->from('version_supported_languages')
                        ->join('game_versions', function ($join) {
                            $join->on('game_versions.id', '=', 'version_supported_languages.game_version_id')
                                ->where('game_versions.is_latest', true);
                        })
                        ->whereColumn('game_versions.game_id', 'games.id')
                        ->whereIn('version_supported_languages.iso_code', $decodedLanguages);
                });
            })
            ->when($this->nsfw || $this->sfw, function ($q) {
                if ($this->sfw && ! $this->nsfw) {
                    $q->where('games.is_nsfw', false);
                } elseif (! $this->sfw && $this->nsfw) {
                    $q->where('games.is_nsfw', true);
                }
            });

        // Handle sorting
        $column = match ($this->sortField) {
            'latest_version_published_at' => 'latest_versions.published_at',
            'english_word_count' => 'english_stats.words',
            'rating_count' => 'latest_versions.rating_count',
            default => "games.{$this->sortField}"
        };

        // Now apply the sort direction + NULLS LAST
        $query->orderByRaw("{$column} {$this->sortDirection} NULLS LAST");

        $games = $query->paginate($this->perPage);

        // Transform supported languages into collection
        foreach ($games as $game) {
            $game->supported_languages = collect($game->supported_languages);
        }

        $this->games = $games;

        $metaTags = $this->getMetaTags();
        app('view')->share('metaTags', $metaTags);
        $this->updateMeta($metaTags);

        return view('livewire.game-list', [
            'games' => $games,
            'metaTags' => $metaTags,
            ...$this->getFilterOptions(),
            'noindex' => $this->showHidden,
        ]);
    }

    public function getMetaTags(): array
    {
        return [
            'title' => $this->getMetaTitle(),
            'description' => $this->getMetaDescription(),
            'image' => $this->getMetaImage(),
        ];
    }

    public function resetSort(): void
    {
        $this->sortField = 'latest_version_published_at';
        $this->sortDirection = 'desc';
        $this->resetPage();
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

    protected function decodeFilterValue(string $value): string
    {
        return rawurldecode($value);
    }

    protected function updateMeta(array $metaTags): void
    {
        if (method_exists($this, 'dispatch')) {
            $this->dispatch('updateMetaTags', metaTags: $metaTags);
        }
    }

    protected function getFilterOptions(): array
    {
        if (! empty(self::$filterOptions)) {
            return self::$filterOptions;
        }

        $visibilityScope = function ($query) {
            return ! $this->showHidden
                ? $query->where('is_visible', true)
                : $query;
        };

        // Cache the status and engine options for 1 hour - they change infrequently
        self::$filterOptions = Cache::remember('game-filter-options', 3600, function () use ($visibilityScope) {
            $baseQuery = Game::query()->tap($visibilityScope);

            return [
                'statuses' => $baseQuery->clone()
                    ->select('status')
                    ->whereNotNull('status')
                    ->distinct()
                    ->orderBy('status')
                    ->pluck('status')
                    ->mapWithKeys(fn ($status) => [$this->encodeFilterValue($status) => $status])
                    ->all(),

                'gameEngines' => $baseQuery->clone()
                    ->select('game_engine')
                    ->whereNotNull('game_engine')
                    ->distinct()
                    ->orderBy('game_engine')
                    ->pluck('game_engine')
                    ->mapWithKeys(fn ($engine) => [$this->encodeFilterValue($engine) => $engine])
                    ->all(),

                'platforms' => [
                    'windows' => 'Windows',
                    'linux' => 'Linux',
                    'mac' => 'Mac',
                    'android' => 'Android',
                    'web' => 'Web',
                ],
            ];
        });

        // Cache languages for 24 hours since they change very rarely
        self::$filterOptions['languages'] = Cache::remember('game-languages', 86400, function () {
            return Language::query()
                ->whereExists(function ($query) {
                    $query->select('version_supported_languages.id')
                        ->from('version_supported_languages')
                        ->whereColumn('version_supported_languages.iso_code', 'iso_639_3_languages.id')
                        ->limit(1);
                })
                ->orderBy('ref_name')
                ->get()
                ->mapWithKeys(fn ($lang) => [
                    $lang->id => [
                        'ref_name' => $lang->ref_name,
                        'flag_code' => $lang->flag_code,
                    ],
                ])
                ->all();
        });

        return self::$filterOptions;
    }

    protected function encodeFilterValue(string $value): string
    {
        return rawurlencode($value);
    }
}
