<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Game;
use App\Traits\HasSocialMetaTags;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class GameList extends Component
{
    use WithPagination, HasSocialMetaTags;

    public string $search = '';
    public array $selectedStatuses = [];
    public array $selectedEngines = [];
    public array $selectedPlatforms = [];
    public bool $nsfw = false;
    public string $sortField = 'version_published_at';
    public string $sortDirection = 'desc';
    public string|int $perPage = 12; // Changed from int type to allow any value temporarily
    public int $page = 1;

    protected array $validPerPageValues = [12, 24, 36];

    protected $queryString = [
        'search' => ['except' => ''],
        'selectedStatuses' => ['except' => []],
        'selectedEngines' => ['except' => []],
        'selectedPlatforms' => ['except' => []],
        'nsfw' => ['except' => false],
        'sortField' => ['except' => 'version_published_at'],
        'sortDirection' => ['except' => 'desc'],
        'perPage' => ['except' => 12],
        'page' => ['except' => 1],
    ];

    private LengthAwarePaginator $games;

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

    protected function normalizePerPage(): void
    {
        // Convert to integer if possible
        $intValue = filter_var($this->perPage, FILTER_VALIDATE_INT);

        // If not a valid integer or not in allowed values, reset to default
        if ($intValue === false || !in_array($intValue, $this->validPerPageValues)) {
            $this->perPage = $this->validPerPageValues[0];
            return;
        }

        $this->perPage = $intValue;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    protected function encodeFilterValue(string $value): string
    {
        return rawurlencode($value);
    }

    protected function decodeFilterValue(string $value): string
    {
        return rawurldecode($value);
    }

    public function toggleFilter(string $type, string $value): void
    {
        $property = match($type) {
            'status' => 'selectedStatuses',
            'engine' => 'selectedEngines',
            'platform' => 'selectedPlatforms',
            default => null,
        };

        if ($property === null) {
            return;
        }

        // Decode the incoming value
        $decodedValue = $this->decodeFilterValue($value);
        $array = array_map([$this, 'decodeFilterValue'], $this->{$property});

        if (in_array($decodedValue, $array)) {
            $array = array_diff($array, [$decodedValue]);
        } else {
            $array[] = $decodedValue;
        }

        // Encode values before saving to property
        $this->{$property} = array_map([$this, 'encodeFilterValue'], array_values($array));
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->selectedStatuses = [];
        $this->selectedEngines = [];
        $this->selectedPlatforms = [];
        $this->nsfw = false;
        $this->sortField = 'version_published_at';
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

    public function getMetaTags(): array
    {
        return [
            'title' => $this->getMetaTitle(),
            'description' => $this->getMetaDescription(),
            'image' => $this->getMetaImage(),
        ];
    }

    public function render(): View
    {
        $query = Game::query()
            ->when(!auth()->user()?->can('viewHidden', Game::class), fn($q) => $q->where('visible', true))
            ->when($this->search, function($q) {
                $q->where(function(Builder $query) {
                    $query->where('name', 'ilike', "%{$this->search}%")
                        ->orWhere('authors', 'ilike', "%{$this->search}%")
                        ->orWhere('tags', 'ilike', "%{$this->search}%")
                        ->orWhere('custom_tags', 'ilike', "%{$this->search}%");
                });
            })
            ->when(!empty($this->selectedStatuses), function($q) {
                $decodedStatuses = array_map([$this, 'decodeFilterValue'], $this->selectedStatuses);
                $q->whereIn('status', $decodedStatuses);
            })
            ->when(!empty($this->selectedEngines), function($q) {
                $decodedEngines = array_map([$this, 'decodeFilterValue'], $this->selectedEngines);
                $q->whereIn('game_engine', $decodedEngines);
            })
            ->when(!empty($this->selectedPlatforms), function($q) {
                foreach ($this->selectedPlatforms as $platform) {
                    $decodedPlatform = $this->decodeFilterValue($platform);
                    $q->where("platform_{$decodedPlatform}", true);
                }
            })
            ->when($this->nsfw, fn($q) => $q->where('nsfw', true));

        if (in_array($this->sortField, ['rating', 'rating_count', 'stats_words'])) {
            $query->orderByRaw("{$this->sortField} {$this->sortDirection} NULLS LAST");
        } else {
            $query->orderBy($this->sortField, $this->sortDirection);
        }

        $this->games = $query->paginate($this->perPage);
        $metaTags = $this->getMetaTags();
        app('view')->share('metaTags', $metaTags);
        $this->updateMeta($metaTags);

        return view('livewire.game-list', [
            'games' => $this->games,
            'metaTags' => $metaTags,
            ...$this->getFilterOptions(),
        ]);
    }

    protected function updateMeta(array $metaTags): void
    {
        if (method_exists($this, 'dispatch')) {
            $this->dispatch('updateMetaTags', metaTags: $metaTags);
        }
    }

    public function getFilterOptions(): array
    {
        $baseQuery = Game::query()
            ->when(!auth()->user()?->can('viewHidden', Game::class), fn($q) => $q->where('visible', true));

        return [
            'statuses' => $baseQuery->clone()
                ->whereNotNull('status')
                ->distinct()
                ->orderBy('status')
                ->pluck('status')
                ->mapWithKeys(fn($status) => [$this->encodeFilterValue($status) => $status])
                ->all(),

            'gameEngines' => $baseQuery->clone()
                ->whereNotNull('game_engine')
                ->distinct()
                ->orderBy('game_engine')
                ->pluck('game_engine')
                ->mapWithKeys(fn($engine) => [$this->encodeFilterValue($engine) => $engine])
                ->all(),

            'platforms' => [
                'windows' => 'Windows',
                'linux' => 'Linux',
                'mac' => 'Mac',
                'android' => 'Android',
                'web' => 'Web',
            ],
        ];
    }

    // In GameList.php
    public function resetSort(): void
    {
        $this->sortField = 'version_published_at';
        $this->sortDirection = 'desc';
        $this->resetPage();
    }
}
