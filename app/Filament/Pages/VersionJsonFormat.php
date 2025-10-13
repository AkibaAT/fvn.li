<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class VersionJsonFormat extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Content Management';

    protected static ?string $navigationLabel = 'Version JSON Format';

    protected static ?int $navigationSort = 100;

    protected static ?string $title = 'Game Version JSON Format';

    protected static ?string $slug = 'version-json-format';

    protected string $view = 'admin.version-json-format';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
