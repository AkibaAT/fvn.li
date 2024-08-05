<?php

declare(strict_types=1);

namespace App\Filament\Resources\RatingResource\Pages;

use App\Filament\Resources\RatingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRatings extends ListRecords
{
    protected static string $resource = RatingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
