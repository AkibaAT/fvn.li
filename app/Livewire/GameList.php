<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Game;
use App\Models\GameJam;
use App\Models\Language;
use App\Models\Tag;
use App\Models\VnList;
use App\Traits\HasDefaultSort;
use App\Traits\HasSocialMetaTags;
use App\Traits\HasSortableColumns;
use App\Traits\SortsVnLists;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class GameList extends Component
{
    use HasDefaultSort, HasSocialMetaTags, HasSortableColumns, SortsVnLists, WithPagination;

    private static array $filterOptions = [];

    protected const array AVAILABLE_SORT_FIELDS = [
        'first_visible_at' => 'Recently Added',
        'latest_version_published_at' => 'Latest Update',
        'initially_published_at' => 'Initial Release',
        'english_word_count' => 'Word Count',
        'rating_count' => 'Review Count',
        'name' => 'Name',
        'trending' => 'Trending',
    ];

    public string $search = '';

    public array $selectedStatuses = [];

    public array $selectedEngines = [];

    public array $selectedPlatforms = [];

    public array $selectedLanguages = [];

    public array $selectedGameJams = [];

    public array $selectedTags = [];

    public bool $nsfw = false;

    public bool $sfw = false;

    public bool $showPaid = false;

    public bool $showFree = false;

    public bool $showDemo = false;

    public bool $showSuspended = false;

    public bool $showHidden = false;

    public string $sortField = self::DEFAULT_SORT_FIELD;

    public string $sortDirection = self::DEFAULT_SORT_DIRECTION;

    public string|int $perPage = 9;

    public int $page = 1;

    protected array $validPerPageValues = [9, 18, 27];

    protected $queryString = [
        'search' => ['except' => ''],
        'selectedStatuses' => ['except' => []],
        'selectedEngines' => ['except' => []],
        'selectedPlatforms' => ['except' => []],
        'selectedLanguages' => ['except' => []],
        'selectedGameJams' => ['except' => []],
        'selectedTags' => ['except' => []],
        'nsfw' => ['except' => false],
        'sfw' => ['except' => false],
        'showPaid' => ['except' => false],
        'showFree' => ['except' => false],
        'showDemo' => ['except' => false],
        'showSuspended' => ['except' => false],
        'sortField' => ['except' => self::DEFAULT_SORT_FIELD],
        'sortDirection' => ['except' => self::DEFAULT_SORT_DIRECTION],
        'perPage' => ['except' => 9],
        'page' => ['except' => 1],
        'showHidden' => ['except' => false],
    ];

    protected $casts = [
        'selectedStatuses' => 'array',
        'selectedEngines' => 'array',
        'selectedPlatforms' => 'array',
        'selectedLanguages' => 'array',
        'selectedGameJams' => 'array',
        'selectedTags' => 'array',
    ];

    private LengthAwarePaginator $games;

    // Add a method to bust the cache when needed (e.g., when a new game is added)
    public static function clearFilterCache(): void
    {
        Cache::forget('game-filter-options');
        Cache::forget('game-languages');
        Cache::forget('game-jams');
        Cache::forget('game-tags');
        self::$filterOptions = [];
    }

    public function mount(): void
    {
        $this->normalizePerPage();
        $this->normalizeArrayProperties();
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
            'gamejam' => 'selectedGameJams',
            'tag' => 'selectedTags',
            default => null,
        };

        if ($property === null) {
            return;
        }

        // Get the current array of values
        $array = $this->{$property};

        // Check if the value exists in the array
        $key = array_search($value, $array);

        if ($key !== false) {
            // Remove if exists
            unset($array[$key]);
        } else {
            // Add if not exists
            $array[] = $value;
        }

        // Re-index the array
        $this->{$property} = array_values($array);
        $this->resetPage();

        // If this is a game jam filter, dispatch an event to update the Alpine component
        if ($type === 'gamejam') {
            $this->dispatch('gameJamFiltersUpdated', selectedGameJams: $this->selectedGameJams);
        }
    }

    public function clearFilters(): void
    {
        $this->selectedStatuses = [];
        $this->selectedEngines = [];
        $this->selectedPlatforms = [];
        $this->selectedLanguages = [];
        $this->selectedGameJams = [];
        $this->selectedTags = [];
        $this->nsfw = false;
        $this->showPaid = false;
        $this->showFree = false;
        $this->showDemo = false;
        $this->showSuspended = false;

        // Dispatch an event to notify Alpine components that filters were cleared
        $this->dispatch('filtersCleared');
        $this->sfw = false;
        $this->sortField = self::getDefaultSortField();
        $this->sortDirection = self::getDefaultSortDirection();
        $this->showHidden = false;
        $this->resetPage();
    }

    public function sortBy($field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            // Default to descending for all fields except 'name'
            $this->sortDirection = $field === 'name' ? 'asc' : 'desc';
        }
    }

    public function render(): View
    {
        $query = Game::query()
            ->select([
                'games.*',
                'latest_versions.published_at as latest_version_published_at',
                'latest_versions.id as latest_version_id',
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
                    AND vsl.is_available = true
                ) as supported_languages'),
            ])
            ->leftJoin('game_versions as latest_versions', function ($join) {
                $join->on('games.id', '=', 'latest_versions.game_id')
                    ->where('latest_versions.is_latest', true);
            })
            ->leftJoin('version_language_stats as english_stats', function ($join) {
                $join->on('latest_versions.id', '=', 'english_stats.game_version_id')
                    ->where('english_stats.iso_code', '=', 'eng');
            })
            ->leftJoinSub(
                DB::table('click_stats')
                    ->selectRaw('COUNT(*) as trending_score, game_id')
                    ->where('type', 'page_view')
                    ->where('clicked_at', '>=', DB::raw("NOW() - INTERVAL '14 days'"))
                    ->groupBy('game_id'),
                'trending',
                function ($join) {
                    $join->on('games.id', '=', 'trending.game_id');
                }
            )
            ->addSelect(DB::raw('COALESCE(trending.trending_score, 0) as trending_score'));

        // Apply filters
        $query->when(! $this->showHidden, fn ($q) => $q->where('is_visible', true))
            ->when($this->search, function ($q) {
                $searchTerm = "%{$this->search}%";
                $q->where(function (Builder $query) use ($searchTerm) {
                    $query->where('games.name', 'ilike', $searchTerm)
                        ->orWhere('games.authors', 'ilike', $searchTerm)
                        ->orWhere('games.custom_tags', 'ilike', $searchTerm);
                });
            })
            ->when(! empty($this->selectedStatuses), function ($q) {
                $q->whereIn('games.status', $this->selectedStatuses);
            })
            ->when(! empty($this->selectedEngines), function ($q) {
                $q->whereIn('games.game_engine', $this->selectedEngines);
            })
            ->when(! empty($this->selectedPlatforms), function ($q) {
                foreach ($this->selectedPlatforms as $platform) {
                    $q->where("latest_versions.is_{$platform}", true);
                }
            })
            ->when(! empty($this->selectedLanguages), function ($q) {
                $q->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('version_supported_languages')
                        ->join('game_versions', function ($join) {
                            $join->on('game_versions.id', '=', 'version_supported_languages.game_version_id')
                                ->where('game_versions.is_latest', true);
                        })
                        ->whereColumn('game_versions.game_id', 'games.id')
                        ->whereIn('version_supported_languages.iso_code', $this->selectedLanguages)
                        ->where('version_supported_languages.is_available', true);
                });
            })
            ->when(! empty($this->selectedGameJams), function ($q) {
                $q->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('game_game_jam')
                        ->whereColumn('game_game_jam.game_id', 'games.id')
                        ->whereIn('game_game_jam.game_jam_id', $this->selectedGameJams);
                });
            })
            ->when(! empty($this->selectedTags), function ($q) {
                $q->whereHas('tags', function ($query) {
                    $query->whereIn('tags.id', $this->selectedTags);
                }, '>=', count($this->selectedTags));
            })
            ->when($this->nsfw || $this->sfw, function ($q) {
                if ($this->sfw && ! $this->nsfw) {
                    $q->where('games.is_nsfw', false);
                } elseif (! $this->sfw && $this->nsfw) {
                    $q->where('games.is_nsfw', true);
                }
            })
            ->when($this->showPaid || $this->showFree, function ($q) {
                if ($this->showPaid && ! $this->showFree) {
                    $q->where('games.is_paid', true);
                } elseif (! $this->showPaid && $this->showFree) {
                    $q->where('games.is_paid', false);
                }
            })
            ->when($this->showDemo, function ($q) {
                $q->where('games.has_demo', true);
            })
            ->when($this->showSuspended, function ($q) {
                $q->where('games.is_suspended', true);
            });

        // Handle sorting
        $column = match ($this->sortField) {
            'latest_version_published_at' => 'latest_versions.published_at',
            'english_word_count' => 'english_stats.words',
            'trending' => 'trending_score',
            'rating_count' => 'games.rating_count',
            'rating' => 'games.rating_score',
            default => "games.{$this->sortField}"
        };

        // Now apply the sort direction + NULLS LAST
        $query->orderByRaw("{$column} {$this->sortDirection} NULLS LAST");

        $games = $query->paginate($this->perPage);

        // Eager load game jams for all games in the current page
        $games->load(['gameJams']);

        // Transform supported languages into collection
        foreach ($games as $game) {
            $game->supported_languages = collect($game->supported_languages);
        }

        $this->games = $games;

        $metaTags = $this->getMetaTags();
        app('view')->share('metaTags', $metaTags);
        $this->updateMeta($metaTags);

        // Get user lists for authenticated users
        $userLists = null;
        if (Auth::check()) {
            // Get all user lists
            $userLists = VnList::where('user_id', Auth::id())
                ->with(['entries' => function ($query) use ($games) {
                    $query->whereIn('game_id', $games->pluck('id'));
                }])
                ->get();

            // Apply the custom list ordering
            if ($userLists->isNotEmpty()) {
                $userLists = $this->sortListsByType($userLists);
            }
        }

        return view('games.list.index', [
            'games' => $games,
            'metaTags' => $metaTags,
            'userLists' => $userLists,
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
        $this->sortField = self::getDefaultSortField();
        $this->sortDirection = self::getDefaultSortDirection();
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

        // Get all games that have at least one version
        $gameIds = DB::table('game_versions')
            ->where('is_latest', true)
            ->pluck('game_id');

        $games = Game::whereIn('id', $gameIds)
            ->select([
                'status',
                'game_engine',
                'is_nsfw',
                'is_paid',
                'has_demo',
            ])
            ->get();

        $statuses = $games->pluck('status')
            ->unique()
            ->filter()
            ->sort()
            ->mapWithKeys(fn ($status) => [
                $status => $status,
            ]);

        $engines = $games->pluck('game_engine')
            ->unique()
            ->filter()
            ->sort()
            ->mapWithKeys(fn ($engine) => [
                $engine => $engine,
            ]);

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

        // Cache game jams for 1 hour
        self::$filterOptions['gameJams'] = Cache::remember('game-jams', 3600, function () use ($visibilityScope) {
            // Get game jams that have games associated with them
            return GameJam::query()
                ->whereExists(function ($query) use ($visibilityScope) {
                    $query->select('game_game_jam.id')
                        ->from('game_game_jam')
                        ->join('games', function ($join) use ($visibilityScope) {
                            $join->on('games.id', '=', 'game_game_jam.game_id');
                            $visibilityScope($join);
                        })
                        ->whereColumn('game_game_jam.game_jam_id', 'game_jams.id')
                        ->limit(1);
                })
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn ($jam) => [
                    (string) $jam->id => $jam->name,
                ])
                ->all();
        });

        // Cache tags for 1 hour
        self::$filterOptions['tags'] = Cache::remember('game-tags', 3600, function () {
            return Tag::query()
                ->withCount('games')
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn ($tag) => [
                    (string) $tag->id => $tag->name . ' (' . $tag->games_count . ')',
                ])
                ->all();
        });

        // Add statuses and engines to the filter options
        self::$filterOptions['statuses'] = $statuses->all();
        self::$filterOptions['gameEngines'] = $engines->all();
        self::$filterOptions['platforms'] = [
            'windows' => 'Windows',
            'linux' => 'Linux',
            'mac' => 'macOS',
            'android' => 'Android',
            'web' => 'Web',
        ];

        return self::$filterOptions;
    }

    private function normalizeArrayProperties(): void
    {
        $arrayProperties = [
            'selectedTags',
            'selectedStatuses',
            'selectedEngines',
            'selectedPlatforms',
            'selectedLanguages',
            'selectedGameJams',
        ];

        foreach ($arrayProperties as $property) {
            $value = $this->{$property};

            // If it's already an array, ensure all values are strings
            if (is_array($value)) {
                $this->{$property} = collect($value)->map(fn ($id) => (string) $id)->toArray();
            }
            // If it's a string, try to convert it to an array
            elseif (is_string($value)) {
                // Handle comma-separated values
                if (str_contains($value, ',')) {
                    $this->{$property} = collect(explode(',', $value))
                        ->map(fn ($id) => (string) trim($id))
                        ->filter()
                        ->toArray();
                } else {
                    // Single value
                    $this->{$property} = $value ? [(string) $value] : [];
                }
            }
            // Ensure it's always an array
            else {
                $this->{$property} = [];
            }
        }
    }
}
