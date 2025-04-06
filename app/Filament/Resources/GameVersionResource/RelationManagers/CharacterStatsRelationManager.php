<?php

declare(strict_types=1);

namespace App\Filament\Resources\GameVersionResource\RelationManagers;

use App\Models\VersionCharacterStats;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CharacterStatsRelationManager extends RelationManager
{
    protected static string $relationship = 'characterStatsWithoutPlaceholders';

    protected static ?string $recordTitleAttribute = 'character.display_names';

    protected static ?string $title = 'Character Statistics';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Read-only view, no form fields needed
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('character.display_names')
                    ->label('Character Name')
                    ->formatStateUsing(function (VersionCharacterStats $record): string {
                        $displayName = $record->character->getDisplayName($record->gameVersion->game->source_language_id);

                        return $displayName ?? 'Unknown';
                    })
                    ->searchable(query: function ($query, $search) {
                        return $query->whereHas('character', function ($query) use ($search) {
                            $query->where('display_names', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(),
                TextColumn::make('language.ref_name')
                    ->label('Language')
                    ->formatStateUsing(function (VersionCharacterStats $record): string {
                        return $record->language->ref_name ?? $record->iso_code;
                    })
                    ->sortable(),
                TextColumn::make('blocks')
                    ->label('Blocks')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('words')
                    ->label('Words')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('iso_code')
                    ->label('Language')
                    ->relationship('language', 'ref_name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->defaultSort('character.display_names')
            ->paginated([10, 25, 50, 100]);
    }
}
