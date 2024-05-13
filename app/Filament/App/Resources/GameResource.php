<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\GameResource\Pages;
use App\Models\Game;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GameResource extends Resource
{
    protected static ?string $model = Game::class;

    protected static ?string $slug = '/';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $query->with('raters')->where('visible', true);
            })
            ->columns([
                Tables\Columns\ImageColumn::make('thumb_url')
                    ->width('125px')
                    ->height('100px')
                    ->label('Thumbnail')
                    ->url(fn (Game $record) => ($record->devlog ?: $record->url))
                    ->openUrlInNewTab(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->url(fn (Game $record) => ($record->devlog ?: $record->url))
                    ->openUrlInNewTab(),
                Tables\Columns\TextColumn::make('raters.name')
                    ->label('Creators')
                    ->searchable(),
                Tables\Columns\TextColumn::make('initially_published_at')
                    ->width('1%')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('version_published_at')
                    ->width('1%')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('version')
                    ->url(fn (Game $record) => GameVersionResource::getUrl('index', ['tableFilters' => ['game' => ['value' => $record->id]]])),
                Tables\Columns\TextColumn::make('stats_blocks')
                    ->numeric()
                    ->sortable(true, fn ($query, $direction) => $query->orderByRaw('stats_blocks ' . $direction . ' NULLS LAST'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('stats_menus')
                    ->numeric()
                    ->sortable(true, fn ($query, $direction) => $query->orderByRaw('stats_menus ' . $direction . ' NULLS LAST'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('stats_options')
                    ->numeric()
                    ->sortable(true, fn ($query, $direction) => $query->orderByRaw('stats_options ' . $direction . ' NULLS LAST'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('stats_words')
                    ->numeric()
                    ->sortable(true, fn ($query, $direction) => $query->orderByRaw('stats_words ' . $direction . ' NULLS LAST')),
                Tables\Columns\TextColumn::make('status')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('rating')
                    ->width('1%')
                    ->numeric()
                    ->sortable()
                    ->url(fn (Game $record) => RatingResource::getUrl('index', ['tableFilters' => ['game' => ['value' => $record->id]]])),
                Tables\Columns\TextColumn::make('rating_count')
                    ->width('1%')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('nsfw')
                    ->disabledClick()
                    ->tooltip('Filter by NSFW status')
                    ->extraAttributes(function (Game $record) {
                        return [
                            'wire:click' => '$set("tableFilters.nsfw.value", "' . (int) $record->nsfw . '")',
                            'class' => 'transition hover:text-primary-500 cursor-pointer',
                        ];
                    })
                    ->label('NSFW')
                    ->width('1%')
                    ->boolean(),
                Tables\Columns\TextColumn::make('tags')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('languages')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('platform_windows')
                    ->label('Windows')
                    ->boolean(),
                Tables\Columns\IconColumn::make('platform_linux')
                    ->label('Linux')
                    ->boolean(),
                Tables\Columns\IconColumn::make('platform_mac')
                    ->label('Mac')
                    ->boolean(),
                Tables\Columns\IconColumn::make('platform_android')
                    ->label('Android')
                    ->boolean(),
                Tables\Columns\IconColumn::make('platform_web')
                    ->label('Web')
                    ->boolean(),
                Tables\Columns\TextColumn::make('game_engine')
                    ->toggleable(isToggledHiddenByDefault: true),
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
            ->defaultSort('version_published_at', 'desc')
            ->paginated([10, 25, 50]);
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
            'index' => Pages\ListGames::route('/'),
        ];
    }
}
