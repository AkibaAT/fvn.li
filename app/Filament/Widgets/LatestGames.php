<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Game;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestGames extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Game::query()->latest()->limit(10)
            )
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('status')
                    ->sortable(),
                IconColumn::make('is_visible')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Action::make('view')
                    ->url(fn (Game $record): string => route('games.show', $record))
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->openUrlInNewTab(),
            ]);
    }
}
