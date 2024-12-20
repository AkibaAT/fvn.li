<?php

namespace App\Livewire;

use App\Models\Game;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class GameList extends Component
{
    use WithPagination;

    public string $search = '';
    public array $selectedStatuses = [];
    public array $selectedEngines = [];
    public array $selectedPlatforms = [];
    public bool $nsfw = false;
    public string $sortField = 'version_published_at';
    public string $sortDirection = 'desc';
    public int $perPage = 10;

    protected $queryString = [
        'search' => ['except' => ''],
        'selectedStatuses' => ['except' => []],
        'selectedEngines' => ['except' => []],
        'selectedPlatforms' => ['except' => []],
        'nsfw' => ['except' => false],
        'sortField' => ['except' => 'version_published_at'],
        'sortDirection' => ['except' => 'desc'],
        'perPage' => ['except' => 10],
        'page' => ['except' => 1],
    ];

    public function updatingSearch()
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
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function render()
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

        // Handle sorting with NULLS LAST for specific fields
        if (in_array($this->sortField, ['rating', 'rating_count', 'stats_words'])) {
            $query->orderByRaw("{$this->sortField} {$this->sortDirection} NULLS LAST");
        } else {
            $query->orderBy($this->sortField, $this->sortDirection);
        }

        return view('livewire.game-list', [
            'games' => $query->paginate($this->perPage),
            ...$this->getFilterOptions(),
        ]);
    }

    private function getFilterOptions(): array
    {
        $baseQuery = Game::query()
            ->when(!auth()->user()?->can('viewHidden', Game::class), fn($q) => $q->where('visible', true));

        return [
            'statuses' => $baseQuery->clone()
                ->whereNotNull('status')
                ->distinct()
                ->orderBy('status')
                ->pluck('status')
                ->mapWithKeys(fn($status) => [$status => $status])
                ->all(),

            'gameEngines' => $baseQuery->clone()
                ->whereNotNull('game_engine')
                ->distinct()
                ->orderBy('game_engine')
                ->pluck('game_engine')
                ->mapWithKeys(fn($engine) => [$engine => $engine])
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
}
