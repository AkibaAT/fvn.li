<?php

declare(strict_types=1);

namespace App\Filament\Resources\GameVersions\Pages;

use App\Filament\Resources\GameVersions\GameVersionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGameVersions extends ListRecords
{
    protected static string $resource = GameVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
