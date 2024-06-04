<?php

declare(strict_types=1);

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\GameResource\Pages;
use App\Models\Game;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
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
                $query->where('visible', true);
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
                Tables\Columns\TextColumn::make('authors')
                    ->searchable()
                    ->sortable()
                    ->html(),
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
                    ->sortable(true, fn ($query, $direction) => $query->orderByRaw('rating ' . $direction . ' NULLS LAST'))
                    ->url(fn (Game $record) => RatingResource::getUrl('index', ['tableFilters' => ['game' => ['value' => $record->id]]])),
                Tables\Columns\TextColumn::make('rating_count')
                    ->width('1%')
                    ->numeric()
                    ->sortable(true, fn ($query, $direction) => $query->orderByRaw('rating_count ' . $direction . ' NULLS LAST'))
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
                    ->disabledClick()
                    ->tooltip('Filter by Windows status')
                    ->extraAttributes(function (Game $record) {
                        return [
                            'wire:click' => '$set("tableFilters.platform_windows.value", "' . (int) $record->platform_windows . '")',
                            'class' => 'transition hover:text-primary-500 cursor-pointer',
                        ];
                    })
                    ->label('Windows')
                    ->boolean(),
                Tables\Columns\IconColumn::make('platform_linux')
                    ->disabledClick()
                    ->tooltip('Filter by Linux status')
                    ->extraAttributes(function (Game $record) {
                        return [
                            'wire:click' => '$set("tableFilters.platform_linux.value", "' . (int) $record->platform_linux . '")',
                            'class' => 'transition hover:text-primary-500 cursor-pointer',
                        ];
                    })
                    ->label('Linux')
                    ->boolean(),
                Tables\Columns\IconColumn::make('platform_mac')
                    ->disabledClick()
                    ->tooltip('Filter by Mac status')
                    ->extraAttributes(function (Game $record) {
                        return [
                            'wire:click' => '$set("tableFilters.platform_mac.value", "' . (int) $record->platform_mac . '")',
                            'class' => 'transition hover:text-primary-500 cursor-pointer',
                        ];
                    })
                    ->label('Mac')
                    ->boolean(),
                Tables\Columns\IconColumn::make('platform_android')
                    ->disabledClick()
                    ->tooltip('Filter by Android status')
                    ->extraAttributes(function (Game $record) {
                        return [
                            'wire:click' => '$set("tableFilters.platform_android.value", "' . (int) $record->platform_android . '")',
                            'class' => 'transition hover:text-primary-500 cursor-pointer',
                        ];
                    })
                    ->label('Android')
                    ->boolean(),
                Tables\Columns\IconColumn::make('platform_web')
                    ->disabledClick()
                    ->tooltip('Filter by Web status')
                    ->extraAttributes(function (Game $record) {
                        return [
                            'wire:click' => '$set("tableFilters.platform_web.value", "' . (int) $record->platform_web . '")',
                            'class' => 'transition hover:text-primary-500 cursor-pointer',
                        ];
                    })
                    ->label('Web')
                    ->boolean(),
                Tables\Columns\TextColumn::make('game_engine')
                    ->disabledClick()
                    ->tooltip('Filter by game engine')
                    ->extraAttributes(function (Game $record) {
                        return [
                            'wire:click' => '$set("tableFilters.game_engine.value", "' . $record->game_engine . '")',
                            'class' => 'transition hover:text-primary-500 cursor-pointer',
                        ];
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('custom_tags')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('nsfw')
                    ->label('NSFW'),
                TernaryFilter::make('platform_windows')
                    ->label('Windows'),
                TernaryFilter::make('platform_linux')
                    ->label('Linux'),
                TernaryFilter::make('platform_mac')
                    ->label('Mac'),
                TernaryFilter::make('platform_android')
                    ->label('Android'),
                TernaryFilter::make('platform_web')
                    ->label('Web'),
                SelectFilter::make('game_engine')
                    ->options([
                        '?' => '?',
                        'Custom' => 'Custom',
                        'Flash' => 'Flash',
                        'GameMaker' => 'GameMaker',
                        'Godot' => 'Godot',
                        'heaps.io' => 'heaps.io',
                        'iFAction Game Maker' => 'iFAction Game Maker',
                        'Kocho' => 'Kocho',
                        'RPG Maker MV' => 'RPG Maker MV',
                        'Ren\'Py' => 'Ren\'Py',
                        'Twine' => 'Twine',
                        'TyranoScript' => 'TyranoScript',
                        'Unity' => 'Unity',
                        'Visual Novel Maker' => 'Visual Novel Maker',
                    ])
                    ->label('Game Engine'),
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
