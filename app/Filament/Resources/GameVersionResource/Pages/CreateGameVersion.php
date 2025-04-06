<?php

declare(strict_types=1);

namespace App\Filament\Resources\GameVersionResource\Pages;

use App\Filament\Resources\GameVersionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGameVersion extends CreateRecord
{
    protected static string $resource = GameVersionResource::class;
}
