<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\GameVersionResource\Pages\CreateGameVersion;
use App\Filament\Resources\GameVersionResource\Pages\EditGameVersion;
use App\Filament\Resources\GameVersionResource\Pages\ListGameVersions;
use App\Filament\Resources\GameVersionResource\Pages\ViewGameVersion;
use App\Filament\Resources\GameVersionResource\RelationManagers\CharacterStatsRelationManager;
use App\Filament\Resources\GameVersionResource\RelationManagers\FileCategoriesRelationManager;
use App\Models\GameVersion;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class GameVersionResource extends Resource
{
    protected static ?string $model = GameVersion::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Content Management';

    protected static ?string $recordTitleAttribute = 'version';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Version Information')
                    ->schema([
                        Select::make('game_id')
                            ->relationship('game', 'name')
                            ->required()
                            ->searchable(),
                        TextInput::make('version')
                            ->required()
                            ->maxLength(20),
                        DateTimePicker::make('published_at')
                            ->required(),
                        TextInput::make('devlog')
                            ->maxLength(250),
                        Toggle::make('is_latest')
                            ->required(),
                    ])->columns(2),

                Section::make('Platform Support')
                    ->schema([
                        Toggle::make('is_windows')
                            ->required(),
                        Toggle::make('is_linux')
                            ->required(),
                        Toggle::make('is_mac')
                            ->required(),
                        Toggle::make('is_android')
                            ->required(),
                        Toggle::make('is_web')
                            ->required(),
                    ])->columns(3),

                Section::make('Ratings')
                    ->schema([
                        TextInput::make('rating')
                            ->numeric()
                            ->step(0.01)
                            ->maxValue(5),
                        TextInput::make('rating_count')
                            ->numeric()
                            ->step(1),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('game.name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('version')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
                IconColumn::make('is_latest')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('rating')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rating_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_latest'),
                TernaryFilter::make('is_windows'),
                TernaryFilter::make('is_linux'),
                TernaryFilter::make('is_mac'),
                TernaryFilter::make('is_android'),
                TernaryFilter::make('is_web'),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('markAsLatest')
                        ->label('Mark as Latest')
                        ->icon('heroicon-o-star')
                        ->action(function (Collection $records): void {
                            // First, unmark all versions for the affected games
                            $gameIds = $records->pluck('game_id')->unique();
                            GameVersion::whereIn('game_id', $gameIds)->update(['is_latest' => false]);

                            // Then mark the selected versions as latest
                            foreach ($records as $record) {
                                $record->is_latest = true;
                                $record->save();
                            }
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            CharacterStatsRelationManager::class,
            FileCategoriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGameVersions::route('/'),
            'create' => CreateGameVersion::route('/create'),
            'view' => ViewGameVersion::route('/{record}'),
            'edit' => EditGameVersion::route('/{record}/edit'),
        ];
    }
}
