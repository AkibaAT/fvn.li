<?php

declare(strict_types=1);

namespace App\Filament\Resources\VersionFileCategoryResource\Pages;

use App\Filament\Resources\VersionFileCategoryResource;
use Filament\Resources\Pages\ListRecords;

class ListVersionFileCategories extends ListRecords
{
    protected static string $resource = VersionFileCategoryResource::class;
}
