<?php

declare(strict_types=1);

namespace App\Filament\Resources\VersionFileCategories;

use App\Filament\Resources\VersionFileCategories\Pages\ListVersionFileCategories;
use App\Filament\Resources\VersionFileCategories\Pages\ViewVersionFileCategory;
use App\Models\VersionFileCategory;
use App\Services\HelperService;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VersionFileCategoryResource extends Resource
{
    protected static ?string $model = VersionFileCategory::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-folder';

    // Hide from navigation menu
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'category';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Category Information')
                    ->schema([
                        TextInput::make('category')
                            ->label('Category')
                            ->disabled(),
                        TextInput::make('total_count')
                            ->label('Total Files')
                            ->disabled(),
                        TextInput::make('total_size')
                            ->label('Total Size')
                            ->formatStateUsing(fn (int $state): string => HelperService::formatBytes($state))
                            ->disabled(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category')
                    ->label('Category')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->sortable(),
                TextColumn::make('total_count')
                    ->label('File Count')
                    ->numeric()
                    ->formatStateUsing(fn (int $state): string => number_format($state))
                    ->sortable(),
                TextColumn::make('total_size')
                    ->label('Total Size')
                    ->formatStateUsing(fn (int $state): string => HelperService::formatBytes($state))
                    ->sortable(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVersionFileCategories::route('/'),
            'view' => ViewVersionFileCategory::route('/{record}'),
        ];
    }
}
