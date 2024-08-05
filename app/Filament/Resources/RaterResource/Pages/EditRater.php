<?php

declare(strict_types=1);

namespace App\Filament\Resources\RaterResource\Pages;

use App\Filament\Resources\RaterResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRater extends EditRecord
{
    protected static string $resource = RaterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
