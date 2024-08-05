<?php

declare(strict_types=1);

namespace App\Filament\Resources\RaterResource\Pages;

use App\Filament\Resources\RaterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRaters extends ListRecords
{
    protected static string $resource = RaterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
