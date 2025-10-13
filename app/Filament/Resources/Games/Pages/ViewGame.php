<?php

declare(strict_types=1);

namespace App\Filament\Resources\Games\Pages;

use App\Filament\Resources\Games\GameResource;
use App\Filament\Resources\GameVersions\GameVersionResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewGame extends ViewRecord
{
    protected static string $resource = GameResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('createVersion')
                ->label('Create Version')
                ->icon('heroicon-o-plus')
                ->color('success')
                ->url(fn () => GameVersionResource::getUrl('create', ['game_id' => $this->record->id])),
        ];
    }
}
