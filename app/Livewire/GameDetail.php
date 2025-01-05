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
    public bool $showAllRatings = false;
    public int $page = 1;

    protected $queryString = [
        'showAllRatings' => ['except' => false],
        'page' => ['except' => 1],
    ];

    public function mount(Game $game): void
    {
        abort_if(!$game->is_visible && !auth()->user()?->can('viewHidden', Game::class), 404);
        $this->game = $game;
    }

    public function toggleRatingsView(): void
    {
        $this->showAllRatings = !$this->showAllRatings;
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

    public function getMetaTags(): array
    {
        // Prepare basic game info for description
        $descriptionParts = [];

        if ($this->game->status) {
            $descriptionParts[] = "A {$this->game->status} game";
        }

        if ($this->game->game_engine) {
            $descriptionParts[] = "made with {$this->game->game_engine}";
        }

        // Add platforms
        $platforms = [];
        foreach (['windows', 'linux', 'mac', 'android', 'web'] as $platform) {
            if ($this->game->{"is_{$platform}"}) {
                $platforms[] = ucfirst($platform);
            }
        }
        if (!empty($platforms)) {
            $descriptionParts[] = "available on " . implode(', ', $platforms);
        }

        // Add word count if available
        $englishWordCount = $this->game->getEnglishWordCount();
        if ($englishWordCount) {
            $descriptionParts[] = number_format($englishWordCount) . " words long";
        }

        // Add rating if available
        if ($this->game->rating_count) {
            $descriptionParts[] = "rated " . number_format($this->game->rating_count) . " times";
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

    public function render(): View
    {
        // Eager load all the relationships we need
        $this->game->load([
            'latestVersion.languageStats.language',
            'gameVersions' => fn($query) => $query->with(['languageStats.language'])->orderByDesc('published_at'),
        ]);

        $ratingsQuery = $this->game->ratings()
            ->where('is_visible', true)
            ->when(!$this->showAllRatings, fn($query) => $query->where('is_reviewed', true))
            ->with('rater')
            ->orderByDesc('published_at');

        $reviews = $ratingsQuery->paginate(10);

        $metaTags = $this->getMetaTags();
        app('view')->share('metaTags', $metaTags);
        $this->updateMeta($metaTags);

        return view('livewire.game-detail', [
            'reviews' => $reviews,
            'versions' => $this->game->gameVersions,
            'latestVersion' => $this->game->latestVersion,
            'englishStats' => $this->game->latestVersion?->getStatsForLanguage('eng'),
            'languageStats' => $this->game->latestVersion?->languageStats ?? collect(),
            'metaTags' => $this->getMetaTags(),
        ]);
    }

    protected function updateMeta(array $metaTags): void
    {
        if (method_exists($this, 'dispatch')) {
            $this->dispatch('updateMetaTags', metaTags: $metaTags);
        }
    }
}
