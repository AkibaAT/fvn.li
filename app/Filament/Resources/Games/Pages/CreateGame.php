<?php

declare(strict_types=1);

namespace App\Filament\Resources\Games\Pages;

use App\Filament\Resources\Games\GameResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGame extends CreateRecord
{
    protected static string $resource = GameResource::class;
}
