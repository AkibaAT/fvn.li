<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\GameVersionResource\Pages;

use App\Filament\App\Resources\GameVersionResource;
use App\Traits\HasSocialMetaTags;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\View\View;

class ListGameVersions extends ListRecords
{
    use HasSocialMetaTags;

    protected static string $resource = GameVersionResource::class;

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

    public function getMetaTags(): array
    {
        return [
            'title' => $this->getMetaTitle(),
            'description' => $this->getMetaDescription(),
            'image' => $this->getMetaImage(),
        ];
    }
}
