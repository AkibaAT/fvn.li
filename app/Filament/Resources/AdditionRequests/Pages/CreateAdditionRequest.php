<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdditionRequests\Pages;

use App\Filament\Resources\AdditionRequests\AdditionRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdditionRequest extends CreateRecord
{
    protected static string $resource = AdditionRequestResource::class;
}
