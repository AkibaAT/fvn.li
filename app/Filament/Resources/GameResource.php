<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\GameResource\Pages\CreateGame;
use App\Filament\Resources\GameResource\Pages\EditGame;
use App\Filament\Resources\GameResource\Pages\ListGames;
use App\Models\Game;
use Filament\Forms\Components\DateTimePicker;
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
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GameResource extends Resource
{
    protected static ?string $model = Game::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                DateTimePicker::make('initially_published_at')
                    ->required(),
                DateTimePicker::make('version_published_at')
                    ->required(),
                TextInput::make('game_id')
                    ->required()
                    ->numeric(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('status')
                    ->maxLength(50),
                Toggle::make('visible')
                    ->required(),
                Toggle::make('nsfw')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('url')
                    ->required()
                    ->maxLength(255),
                TextInput::make('thumb_url')
                    ->maxLength(255),
                TextInput::make('version')
                    ->maxLength(255),
                TextInput::make('tags')
                    ->maxLength(255),
                TextInput::make('rating')
                    ->numeric(),
                TextInput::make('rating_count')
                    ->numeric(),
                TextInput::make('devlog')
                    ->maxLength(255),
                TextInput::make('languages')
                    ->maxLength(255),
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
                TextInput::make('game_engine')
                    ->required()
                    ->maxLength(50),
                Textarea::make('error')
                    ->columnSpanFull(),
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
                TextColumn::make('initially_published_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('version_published_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('game_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('name'),
                TextColumn::make('status')
                    ->searchable(),
                IconColumn::make('visible')
                    ->boolean(),
                IconColumn::make('nsfw')
                    ->boolean(),
                TextColumn::make('url')
                    ->searchable(),
                TextColumn::make('thumb_url')
                    ->searchable(),
                TextColumn::make('version')
                    ->searchable(),
                TextColumn::make('tags')
                    ->searchable(),
                TextColumn::make('rating')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rating_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('devlog')
                    ->searchable(),
                TextColumn::make('languages')
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
                TextColumn::make('game_engine')
                    ->searchable(),
            ])
            ->filters([
                TernaryFilter::make('nsfw')
                    ->label('NSFW'),
                Filter::make('windows')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('platform_windows', true)),
                Filter::make('linux')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('platform_linux', true)),
                Filter::make('mac')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('platform_mac', true)),
                Filter::make('android')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('platform_android', true)),
                Filter::make('web')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('platform_web', true)),
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
            'index' => ListGames::route('/'),
            'create' => CreateGame::route('/create'),
            'edit' => EditGame::route('/{record}/edit'),
        ];
    }
}
