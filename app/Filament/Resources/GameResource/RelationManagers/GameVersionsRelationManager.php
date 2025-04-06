<?php

declare(strict_types=1);

namespace App\Filament\Resources\GameResource\RelationManagers;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class GameVersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'gameVersions';

    protected static ?string $recordTitleAttribute = 'version';

    protected static ?string $title = 'Game Versions';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('version')
                    ->required()
                    ->maxLength(20),
                DateTimePicker::make('published_at')
                    ->required(),
                TextInput::make('devlog')
                    ->maxLength(250),
                Toggle::make('is_latest')
                    ->required(),
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
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('version')
            ->columns([
                TextColumn::make('version')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
                IconColumn::make('is_latest')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('devlog')
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if ($state === null || strlen($state) <= 30) {
                            return null;
                        }

                        return $state;
                    }),
                IconColumn::make('is_windows')
                    ->boolean()
                    ->label('Win'),
                IconColumn::make('is_linux')
                    ->boolean()
                    ->label('Linux'),
                IconColumn::make('is_mac')
                    ->boolean()
                    ->label('Mac'),
                IconColumn::make('is_android')
                    ->boolean()
                    ->label('Android'),
                IconColumn::make('is_web')
                    ->boolean()
                    ->label('Web'),
            ])
            ->filters([
                TernaryFilter::make('is_latest'),
                TernaryFilter::make('is_windows'),
                TernaryFilter::make('is_linux'),
                TernaryFilter::make('is_mac'),
                TernaryFilter::make('is_android'),
                TernaryFilter::make('is_web'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                Action::make('view')
                    ->label('View Details')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => route('filament.admin.resources.game-versions.edit', $record))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('markAsLatest')
                        ->label('Mark as Latest')
                        ->icon('heroicon-o-star')
                        ->action(function ($records) {
                            // First, unmark all versions for this game
                            $gameId = $records->first()->game_id;
                            $this->getRelationship()->where('game_id', $gameId)->update(['is_latest' => false]);

                            // Then mark the selected version as latest
                            $records->first()->update(['is_latest' => true]);
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('published_at', 'desc');
    }
}
