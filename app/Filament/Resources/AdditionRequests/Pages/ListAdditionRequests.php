<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdditionRequests\Pages;

use App\Filament\Resources\AdditionRequests\AdditionRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdditionRequests extends ListRecords
{
    protected static string $resource = AdditionRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
