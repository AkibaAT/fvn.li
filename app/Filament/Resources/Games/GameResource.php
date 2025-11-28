<?php

declare(strict_types=1);

namespace App\Filament\Resources\Games;

use App\Filament\Resources\Games\Pages\CreateGame;
use App\Filament\Resources\Games\Pages\EditGame;
use App\Filament\Resources\Games\Pages\ListGames;
use App\Filament\Resources\Games\Pages\ViewGame;
use App\Filament\Resources\Games\RelationManagers\GameVersionsRelationManager;
use App\Models\Game;
use App\Services\GameDataSyncService;
use App\Services\SteamReviewImportService;
use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use UnitEnum;

class GameResource extends Resource
{
    protected static ?string $model = Game::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-play';

    protected static string|UnitEnum|null $navigationGroup = 'Content Management';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Game Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255),
                        Select::make('platform')
                            ->options([
                                'itch_io' => 'itch.io',
                                'steam' => 'Steam',
                                'other' => 'Other',
                            ])
                            ->required()
                            ->default('itch_io'),
                        TextInput::make('itch_id')
                            ->label('itch.io Game ID')
                            ->numeric()
                            ->nullable()
                            ->visible(fn (callable $get) => $get('platform') === 'itch_io'),
                        TextInput::make('steam_app_id')
                            ->label('Steam App ID')
                            ->numeric()
                            ->nullable()
                            ->visible(fn (callable $get) => $get('platform') === 'steam'),
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
                        KeyValue::make('url')
                            ->label('Platform URLs')
                            ->helperText('URLs for different platforms (itch_io, steam, other)')
                            ->keyLabel('Platform')
                            ->valueLabel('URL')
                            ->columnSpanFull(),
                        TextInput::make('thumb_url')
                            ->label('Thumbnail URL')
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

                Section::make('Pricing & Availability')
                    ->schema([
                        Toggle::make('is_paid')
                            ->label('Is Paid Game')
                            ->helperText('Indicates if this game requires payment to play')
                            ->default(false),
                        TextInput::make('min_price')
                            ->label('Base Price')
                            ->helperText('The original price before any discounts')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->visible(fn (callable $get) => $get('is_paid')),
                        Select::make('currency')
                            ->label('Currency')
                            ->helperText('Currency for the game price')
                            ->options([
                                'USD' => 'USD ($)',
                                'EUR' => 'EUR (€)',
                                'JPY' => 'JPY (¥)',
                            ])
                            ->default('USD')
                            ->searchable()
                            ->visible(fn (callable $get) => $get('is_paid')),
                        Toggle::make('is_on_sale')
                            ->label('Currently On Sale')
                            ->helperText('Indicates if this game is currently discounted')
                            ->default(false)
                            ->visible(fn (callable $get) => $get('is_paid')),
                        TextInput::make('sale_discount_percent')
                            ->label('Sale Discount %')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->helperText('Discount percentage if on sale')
                            ->visible(fn (callable $get) => $get('is_paid') && $get('is_on_sale')),
                        Toggle::make('has_demo')
                            ->label('Has Demo')
                            ->helperText('Indicates if a free demo is available')
                            ->default(false),
                    ])->columns(2),

                Section::make('Dates')
                    ->schema([
                        DateTimePicker::make('initially_published_at')
                            ->label('Initially Published'),
                    ]),
            ]);
    }

    /**
     * @throws Exception
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('platform')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'itch_io' => 'info',
                        'steam' => 'success',
                        'other' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'itch_io' => 'itch.io',
                        'steam' => 'Steam',
                        'other' => 'Other',
                        default => 'Unknown',
                    })
                    ->sortable(),
                TextColumn::make('itch_id')
                    ->label('itch.io ID')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                TextColumn::make('steam_app_id')
                    ->label('Steam App ID')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->sortable(),
                IconColumn::make('is_visible')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_nsfw')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('is_paid')
                    ->boolean()
                    ->label('Paid')
                    ->sortable(),
                IconColumn::make('has_demo')
                    ->boolean()
                    ->label('Demo')
                    ->sortable(),
                TextColumn::make('min_price')
                    ->label('Price')
                    ->formatStateUsing(fn ($record) => $record->formatPrice($record->min_price) ?? 'Free')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                SelectFilter::make('platform')
                    ->options([
                        'itch_io' => 'itch.io',
                        'steam' => 'Steam',
                        'other' => 'Other',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'In development' => 'In development',
                        'Released' => 'Released',
                        'Abandoned' => 'Abandoned',
                        'On hold' => 'On hold',
                    ]),
                TernaryFilter::make('is_visible'),
                TernaryFilter::make('is_nsfw'),
                TernaryFilter::make('is_paid')
                    ->label('Paid Games'),
                TernaryFilter::make('has_demo')
                    ->label('Has Demo'),
            ])
            ->recordActions([
                Action::make('sync')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Sync Game Data')
                    ->modalDescription(fn (Game $record): string => "This will fetch the latest data from {$record->getPlatformName()} for \"{$record->name}\". This may take a few minutes."
                    )
                    ->modalSubmitActionLabel('Sync Now')
                    ->visible(fn (Game $record): bool => $record->platform === 'itch_io' || $record->platform === 'steam'
                    )
                    ->action(function (Game $record): void {
                        try {
                            $syncService = app(GameDataSyncService::class);
                            $syncService->loadFullDetails($record);
                            $record->save();

                            Notification::make()
                                ->title('Game synced successfully')
                                ->body("Data for \"{$record->name}\" has been updated from {$record->getPlatformName()}.")
                                ->success()
                                ->send();
                        } catch (Exception $e) {
                            Notification::make()
                                ->title('Sync failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('importSteamReviews')
                    ->label('Import Reviews')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Import Steam Reviews')
                    ->modalDescription(fn (Game $record): string => "This will import English reviews from Steam for \"{$record->name}\". This may take several minutes."
                    )
                    ->modalSubmitActionLabel('Import Reviews')
                    ->visible(fn (Game $record): bool => $record->platform === 'steam')
                    ->action(function (Game $record): void {
                        try {
                            $importService = app(SteamReviewImportService::class);
                            $stats = $importService->syncAllReviews($record);
                            $importService->updateGameRatingStats($record);

                            Notification::make()
                                ->title('Reviews synced successfully')
                                ->body("Fetched {$stats['fetched']}, imported {$stats['imported']}, updated {$stats['updated']}, deleted {$stats['deleted']}, skipped {$stats['skipped']}, errors: {$stats['errors']}")
                                ->success()
                                ->send();
                        } catch (Exception $e) {
                            Notification::make()
                                ->title('Import failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
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
