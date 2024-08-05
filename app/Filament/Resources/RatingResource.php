<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\RatingResource\Pages\CreateRating;
use App\Filament\Resources\RatingResource\Pages\EditRating;
use App\Filament\Resources\RatingResource\Pages\ListRatings;
use App\Models\Rating;
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

class RatingResource extends Resource
{
    protected static ?string $model = Rating::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                DateTimePicker::make('published_at')
                    ->required(),
                TextInput::make('event_id')
                    ->required()
                    ->numeric(),
                Select::make('game_id')
                    ->searchable()
                    ->relationship('game', 'name')
                    ->optionsLimit(10)
                    ->required(),
                Select::make('rater_id')
                    ->searchable()
                    ->relationship('rater', 'name')
                    ->optionsLimit(10)
                    ->required(),
                TextInput::make('rating')
                    ->required()
                    ->numeric(),
                Textarea::make('review')
                    ->required()
                    ->columnSpanFull(),
                Toggle::make('visible')
                    ->required(),
                Toggle::make('has_review')
                    ->required(),
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
                TextColumn::make('event_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('game.name')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rater.name')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rating')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('visible')
                    ->boolean(),
                IconColumn::make('has_review')
                    ->boolean(),
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
            'index' => ListRatings::route('/'),
            'create' => CreateRating::route('/create'),
            'edit' => EditRating::route('/{record}/edit'),
        ];
    }
}
