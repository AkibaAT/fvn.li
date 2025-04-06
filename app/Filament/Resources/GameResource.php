<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\GameResource\Pages\CreateGame;
use App\Filament\Resources\GameResource\Pages\EditGame;
use App\Filament\Resources\GameResource\Pages\ListGames;
use App\Filament\Resources\GameResource\Pages\ViewGame;
use App\Filament\Resources\GameResource\RelationManagers\GameVersionsRelationManager;
use App\Models\Game;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;

class GameResource extends Resource
{
    protected static ?string $model = Game::class;

    protected static ?string $navigationIcon = 'heroicon-o-play';

    protected static ?string $navigationGroup = 'Content Management';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Game Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('game_id')
                            ->required()
                            ->numeric(),
                        TextInput::make('status')
                            ->required()
                            ->default('In development')
                            ->maxLength(50),
                        Toggle::make('is_visible')
                            ->required(),
                        Toggle::make('is_nsfw')
                            ->required(),
                        Textarea::make('description')
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('URLs & Media')
                    ->schema([
                        TextInput::make('url')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('thumb_url')
                            ->maxLength(255),
                        TextInput::make('game_engine')
                            ->required()
                            ->default('unknown')
                            ->maxLength(50),
                    ]),

                Section::make('Additional Information')
                    ->schema([
                        TextInput::make('authors')
                            ->maxLength(255),
                        TextInput::make('custom_tags')
                            ->default('')
                            ->maxLength(255),
                        TextInput::make('source_language_id')
                            ->maxLength(3),
                    ]),

                Section::make('Dates')
                    ->schema([
                        DateTimePicker::make('initially_published_at')
                            ->label('Initially Published'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('status')
                    ->sortable(),
                IconColumn::make('is_visible')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_nsfw')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('game_engine')
                    ->sortable(),
                TextColumn::make('initially_published_at')
                    ->label('Initially Published')
                    ->dateTime()
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
                SelectFilter::make('status')
                    ->options([
                        'In development' => 'In development',
                        'Released' => 'Released',
                        'Abandoned' => 'Abandoned',
                        'On hold' => 'On hold',
                    ]),
                TernaryFilter::make('is_visible'),
                TernaryFilter::make('is_nsfw'),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('toggleVisibility')
                        ->label('Toggle Visibility')
                        ->icon('heroicon-o-eye')
                        ->action(function (Collection $records): void {
                            foreach ($records as $record) {
                                $record->is_visible = ! $record->is_visible;
                                $record->save();
                            }
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            GameVersionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGames::route('/'),
            'create' => CreateGame::route('/create'),
            'view' => ViewGame::route('/{record}'),
            'edit' => EditGame::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
