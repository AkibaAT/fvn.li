<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\GameResource\Pages;

use App\Filament\App\Resources\GameResource;
use App\Traits\HasSocialMetaTags;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\View\View;

class ListGames extends ListRecords
{
    use HasSocialMetaTags;

    protected static string $resource = GameResource::class;

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
            null => Tab::make('All'),
            'in_development' => Tab::make()->query(fn ($query) => $query->where('status', 'In development')),
            'released' => Tab::make()->query(fn ($query) => $query->where('status', 'Released')),
            'prototype' => Tab::make()->query(fn ($query) => $query->where('status', 'Prototype')),
            'abandoned' => Tab::make()->query(fn ($query) => $query->where('status', 'Abandoned')),
            'canceled' => Tab::make()->query(fn ($query) => $query->where('status', 'Canceled')),
            'on_hold' => Tab::make()->query(fn ($query) => $query->where('status', 'On hold')),
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
