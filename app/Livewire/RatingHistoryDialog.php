<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Rating;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

class RatingHistoryDialog extends Component
{
    public ?Collection $ratings = null;
    public ?string $gameName = null;

    protected $listeners = ['show-rating-history' => 'loadHistory'];

    public function loadHistory($raterId, $gameId): void
    {
        $this->ratings = Rating::query()
            ->where('rater_id', $raterId)
            ->where('game_id', $gameId)
            ->with(['game' => fn ($query) => $query->select('id', 'name')->withoutGlobalScopes()])
            ->orderByDesc('published_at')
            ->get();

        $this->gameName = $this->ratings->first()?->game->name;
    }

    public function render(): View
    {
        return view('livewire.rating-history-dialog');
    }
}
