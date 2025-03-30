<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Rating;
use App\Traits\HasSortableColumns;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class RatingsList extends Component
{
    use HasSortableColumns, WithPagination;

    protected const array AVAILABLE_SORT_FIELDS = [
        'published_at' => 'Date',
        'rating' => 'Rating',
    ];

    public int $raterId;
    public bool $showOnlyReviews = true;
    public bool $showOnlyVisibleGames = false;
    public string $sortField = 'published_at';
    public string $sortDirection = 'desc';
    public int $page = 1;

    protected $queryString = [
        'showOnlyReviews' => ['except' => true],
        'showOnlyVisibleGames' => ['except' => true],
        'sortField' => ['except' => 'published_at'],
        'sortDirection' => ['except' => 'desc'],
        'page' => ['except' => 1],
    ];

    public function mount(int $raterId): void
    {
        $this->raterId = $raterId;
    }

    public function toggleReviewsView(): void
    {
        $this->showOnlyReviews = ! $this->showOnlyReviews;
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function showRatingHistory(int $gameId): void
    {
        $this->dispatch('show-rating-history', raterId: $this->raterId, gameId: $gameId);
        $this->skipRender();
    }

    public function toggleGameVisibility(): void
    {
        $this->showOnlyVisibleGames = ! $this->showOnlyVisibleGames;
        $this->resetPage();
    }

    public function updated($property): void
    {
        if (in_array($property, ['showOnlyReviews', 'showOnlyVisibleGames', 'sortField', 'sortDirection'])) {
            $this->dispatch('filtersUpdated', [
                'showOnlyReviews' => $this->showOnlyReviews,
                'showOnlyVisibleGames' => $this->showOnlyVisibleGames,
                'sortField' => $this->sortField,
                'sortDirection' => $this->sortDirection,
            ]);
        }
    }

    public function render(): View
    {
        $ratings = Rating::query()
            ->select(['ratings.*'])
            ->with(['game:id,game_id,name,url,slug,is_visible'])
            ->where('rater_id', $this->raterId)
            ->where('is_visible', true)
            ->when($this->showOnlyVisibleGames, function ($query) {
                $query->whereHas('game', function ($q) {
                    $q->where('is_visible', true);
                });
            })
            ->when($this->showOnlyReviews, fn ($query) => $query->where('is_reviewed', true))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);

        return view('ratings.raters.ratings-list', [
            'ratings' => $ratings,
            'previousRatingCounts' => $this->getPreviousRatingCounts(),
        ]);
    }

    protected function getPreviousRatingCounts(): array
    {
        return Rating::where('rater_id', $this->raterId)
            ->where('is_visible', false)
            ->whereIn('game_id', function ($query) {
                $query->select('game_id')
                    ->from('ratings')
                    ->where('rater_id', $this->raterId)
                    ->where('is_visible', true);
            })
            ->selectRaw('game_id, count(*) as count')
            ->groupBy('game_id')
            ->get()
            ->pluck('count', 'game_id')
            ->toArray();
    }
}
