<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Game;
use App\Traits\HasSocialMetaTags;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class GameDetail extends Component
{
    use HasSocialMetaTags, WithPagination;

    public Game $game;

    public function mount(Game $game): void
    {
        abort_if(!$game->is_visible && !auth()->user()?->can('viewHidden', Game::class), 404);
        $this->game = $game;
    }

    protected function encodeFilterValue(string $value): string
    {
        return rawurlencode($value);
    }

    protected function decodeFilterValue(string $value): string
    {
        return rawurldecode($value);
    }

    public function getMetaTags(): array
    {
        return [
            'title' => $this->game->name . ' - ' . config('app.name'),
            'description' => substr(strip_tags($this->game->description), 0, 160),
            'image' => $this->game->thumb_url,
        ];
    }

    public function render(): View
    {
        $metaTags = $this->getMetaTags();
        app('view')->share('metaTags', $metaTags);
        $this->updateMeta($metaTags);

        // Eager load all the relationships we need
        $this->game->load([
            'latestVersion.languageStats.language',
            'gameVersions' => fn($query) => $query->with(['languageStats.language'])->orderByDesc('published_at'),
        ]);

        $reviews = $this->game->ratings()
            ->where('is_visible', true)
            ->where('is_reviewed', true)
            ->with('rater')
            ->orderByDesc('published_at')
            ->paginate(10);

        return view('livewire.game-detail', [
            'reviews' => $reviews,
            'versions' => $this->game->gameVersions,
            'latestVersion' => $this->game->latestVersion,
            'englishStats' => $this->game->latestVersion?->getStatsForLanguage('eng'),
            'languageStats' => $this->game->latestVersion?->languageStats ?? collect(),
            'metaTags' => $metaTags,
        ]);
    }

    protected function updateMeta(array $metaTags): void
    {
        if (method_exists($this, 'dispatch')) {
            $this->dispatch('updateMetaTags', metaTags: $metaTags);
        }
    }
}
