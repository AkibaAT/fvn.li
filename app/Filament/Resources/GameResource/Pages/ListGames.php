<?php

declare(strict_types=1);

namespace App\Filament\Resources\GameResource\Pages;

use App\Filament\Resources\GameResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;

class ListGames extends ListRecords
{
    protected static string $resource = GameResource::class;

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

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
