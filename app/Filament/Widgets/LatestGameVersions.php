<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\GameVersion;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestGameVersions extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                GameVersion::query()->with('game')->orderBy('created_at', 'desc')->limit(10)
            )
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('game.name')
                    ->sortable(),
                TextColumn::make('version')
                    ->sortable(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
                IconColumn::make('is_latest')
                    ->boolean()
                    ->sortable(),
            ]);
    }
}
