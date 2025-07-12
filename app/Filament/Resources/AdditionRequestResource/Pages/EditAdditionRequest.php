<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdditionRequestResource\Pages;

use App\Filament\Resources\AdditionRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAdditionRequest extends EditRecord
{
    protected static string $resource = AdditionRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
