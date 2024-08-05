<?php

declare(strict_types=1);

namespace App\Filament\Resources\RatingResource\Pages;

use App\Filament\Resources\RatingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRating extends EditRecord
{
    protected static string $resource = RatingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
