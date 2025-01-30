<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Rater;
use App\Traits\HasSocialMetaTags;
use Illuminate\View\View;
use Livewire\Component;

class RaterDetail extends Component
{
    use HasSocialMetaTags;

    public Rater $rater;
    public int $totalRatingsCount;
    public int $visibleGamesRatingsCount;
    protected $listeners = ['filtersUpdated' => 'handleFiltersUpdated'];

    public function mount(Rater $rater): void
    {
        $this->rater = $rater;
        $this->totalRatingsCount = $rater->ratings()->count();
        $this->visibleGamesRatingsCount = $rater->ratings()
            ->whereHas('game', fn ($q) => $q->where('is_visible', true))
            ->count();
    }

    public function render(): View
    {
        $metaTags = [
            'title' => $this->getMetaTitle(),
            'description' => $this->getMetaDescription(),
            'image' => $this->getMetaImage(),
        ];

        app('view')->share('metaTags', $metaTags);

        $this->updateMeta($metaTags);

        return view('livewire.rater-detail', ['metaTags' => $metaTags])
            ->title($this->getMetaTitle());
    }

    protected function updateMeta(array $metaTags): void
    {
        if (method_exists($this, 'dispatch')) {
            $this->dispatch('updateMetaTags', metaTags: $metaTags);
        }
    }
}
