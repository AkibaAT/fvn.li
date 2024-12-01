<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\RatingResource\Pages;

use App\Filament\App\Resources\RatingResource;
use App\Traits\HasSocialMetaTags;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class ListRatings extends ListRecords
{
    use HasSocialMetaTags;

    protected static string $resource = RatingResource::class;

    public function mount(): void
    {
        parent::mount();
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_START,
            fn (): View => view('partials.social-meta-tags', ['page' => $this]),
        );
    }

    public function updatedTableRecordsPerPage(): void
    {
        if (! in_array($this->getTableRecordsPerPage(), [10, 25, 50])) {
            $this->tableRecordsPerPage = 10;
        }

        parent::updatedTableRecordsPerPage();
    }

    public function getTabs(): array
    {
        return [
            'with_review' => Tab::make()->query(fn (Builder $query) => $query->where('has_review', true)),
            'all' => Tab::make('All Ratings'),
        ];
    }

    public function getMetaTags(): array
    {
        return [
            'title' => $this->getMetaTitle(),
            'description' => $this->getMetaDescription(),
            'image' => $this->getMetaImage(),
        ];
    }
}
