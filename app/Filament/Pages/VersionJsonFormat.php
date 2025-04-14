<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Pages\Page;

class VersionJsonFormat extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'admin.version-json-format';

    protected static ?string $navigationGroup = 'Content Management';

    protected static ?string $navigationLabel = 'Version JSON Format';

    protected static ?int $navigationSort = 100;

    protected static ?string $title = 'Game Version JSON Format';

    protected static ?string $slug = 'version-json-format';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
