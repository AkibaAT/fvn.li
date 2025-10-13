<?php

declare(strict_types=1);

namespace App\Filament\Resources\VersionFileCategories\Pages;

use App\Filament\Resources\VersionFileCategories\VersionFileCategoryResource;
use Filament\Resources\Pages\ListRecords;

class ListVersionFileCategories extends ListRecords
{
    protected static string $resource = VersionFileCategoryResource::class;
}
