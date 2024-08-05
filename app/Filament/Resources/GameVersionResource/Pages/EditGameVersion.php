<?php

declare(strict_types=1);

namespace App\Filament\Resources\GameVersionResource\Pages;

use App\Filament\Resources\GameVersionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGameVersion extends EditRecord
{
    protected static string $resource = GameVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
