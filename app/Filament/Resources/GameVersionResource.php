<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\GameVersionResource\Pages\CreateGameVersion;
use App\Filament\Resources\GameVersionResource\Pages\EditGameVersion;
use App\Filament\Resources\GameVersionResource\Pages\ListGameVersions;
use App\Models\GameVersion;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GameVersionResource extends Resource
{
    protected static ?string $model = GameVersion::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                DateTimePicker::make('published_at')
                    ->required(),
                Select::make('game_id')
                    ->searchable()
                    ->relationship('game', 'name')
                    ->optionsLimit(10)
                    ->required(),
                TextInput::make('version')
                    ->required()
                    ->maxLength(20),
                Textarea::make('devlog')
                    ->required()
                    ->columnSpanFull(),
                Toggle::make('platform_windows')
                    ->required(),
                Toggle::make('platform_linux')
                    ->required(),
                Toggle::make('platform_mac')
                    ->required(),
                Toggle::make('platform_android')
                    ->required(),
                Toggle::make('platform_web')
                    ->required(),
                TextInput::make('stats_blocks')
                    ->numeric(),
                TextInput::make('stats_menus')
                    ->numeric(),
                TextInput::make('stats_options')
                    ->numeric(),
                TextInput::make('stats_words')
                    ->numeric(),
                TextInput::make('rating')
                    ->numeric(),
                TextInput::make('rating_count')
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('game.name')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('version')
                    ->searchable(),
                IconColumn::make('platform_windows')
                    ->boolean(),
                IconColumn::make('platform_linux')
                    ->boolean(),
                IconColumn::make('platform_mac')
                    ->boolean(),
                IconColumn::make('platform_android')
                    ->boolean(),
                IconColumn::make('platform_web')
                    ->boolean(),
                TextColumn::make('stats_blocks')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('stats_menus')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('stats_options')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('stats_words')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rating')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rating_count')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
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
            'index' => ListGameVersions::route('/'),
            'create' => CreateGameVersion::route('/create'),
            'edit' => EditGameVersion::route('/{record}/edit'),
        ];
    }
}
