<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdditionRequests;

use App\Filament\Resources\AdditionRequests\Pages\EditAdditionRequest;
use App\Filament\Resources\AdditionRequests\Pages\ListAdditionRequests;
use App\Models\AdditionRequest;
use App\Models\Game;
use App\Models\User;
use App\Services\GameDataSyncService;
use App\Services\SteamReviewImportService;
use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use UnitEnum;

class AdditionRequestResource extends Resource
{
    protected static ?string $model = AdditionRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Content Management';

    protected static ?string $recordTitleAttribute = 'game_url';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('game_url')
                    ->label('Game URL')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->tooltip(fn (AdditionRequest $record): string => $record->game_url),

                TextColumn::make('platform')
                    ->label('Platform')
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

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        AdditionRequest::STATUS_PENDING => 'warning',
                        AdditionRequest::STATUS_APPROVED => 'success',
                        AdditionRequest::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        AdditionRequest::STATUS_PENDING => 'Pending',
                        AdditionRequest::STATUS_APPROVED => 'Approved',
                        AdditionRequest::STATUS_REJECTED => 'Rejected',
                        default => $state,
                    }),

                TextColumn::make('users_count')
                    ->label('Requesters')
                    ->counts('users')
                    ->sortable(),

                TextColumn::make('game.name')
                    ->label('Linked Game')
                    ->formatStateUsing(function ($state, AdditionRequest $record): string {
                        if (! $record->game) {
                            return 'Not linked';
                        }

                        $platformUrl = $record->game->getPrimaryUrl();
                        $urlDisplay = $platformUrl ? self::extractUrlIdentifier($platformUrl) : 'No URL';
                        return $state . ' (' . $urlDisplay . ')';
                    })
                    ->searchable()
                    ->sortable()
                    ->placeholder('Not linked'),

                TextColumn::make('reviewer.name')
                    ->label('Reviewed By')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Not reviewed'),

                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable()
                    ->since(),

                TextColumn::make('reviewed_at')
                    ->label('Reviewed')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->placeholder('Not reviewed'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        AdditionRequest::STATUS_PENDING => 'Pending',
                        AdditionRequest::STATUS_APPROVED => 'Approved',
                        AdditionRequest::STATUS_REJECTED => 'Rejected',
                    ]),

                Filter::make('has_game')
                    ->label('Has Linked Game')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('game_id')),

                Filter::make('reviewed')
                    ->label('Reviewed')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('reviewed_at')),
            ])
            ->recordActions([
                Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (AdditionRequest $record): bool => $record->isPending())
                    ->action(function (AdditionRequest $record): void {
                        $user = Auth::user();
                        if ($user instanceof User) {
                            $record->approve($user);
                            Notification::make()
                                ->title('Request approved successfully')
                                ->success()
                                ->send();
                        }
                    }),

                Action::make('approve_and_create')
                    ->label('Approve & Create Game')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve & Create Game')
                    ->modalDescription(fn (AdditionRequest $record): string =>
                        "This will approve the request and create a new game entry, then sync data from {$record->platform}. This may take a few minutes."
                    )
                    ->modalSubmitActionLabel('Approve & Create')
                    ->visible(fn (AdditionRequest $record): bool =>
                        $record->isPending() &&
                        !$record->game &&
                        ($record->platform === 'itch_io' || $record->platform === 'steam')
                    )
                    ->action(function (AdditionRequest $record): void {
                        $user = Auth::user();
                        if (!($user instanceof User)) {
                            return;
                        }

                        try {
                            // Create the game
                            $game = new Game();
                            $game->platform = $record->platform;
                            $game->setUrlForPlatform($record->platform, $record->game_url);
                            $game->name = 'Syncing...'; // Temporary name
                            $game->is_visible = false; // Start as hidden
                            $game->save();

                            // Link to the request
                            $record->game_id = $game->id;
                            $record->approve($user);

                            // Sync data
                            $syncService = app(GameDataSyncService::class);
                            $syncService->loadFullDetails($game);
                            $game->save();

                            // Import reviews for Steam games
                            $reviewStats = null;
                            if ($game->platform === 'steam') {
                                try {
                                    $importService = app(SteamReviewImportService::class);
                                    $reviewStats = $importService->importReviews($game, 100);
                                    $importService->updateGameRatingStats($game);
                                } catch (Exception $reviewException) {
                                    Log::error('Failed to import Steam reviews during game creation', [
                                        'game_id' => $game->id,
                                        'error' => $reviewException->getMessage(),
                                    ]);
                                }
                            }

                            $body = "Created \"{$game->name}\" and synced data from {$game->getPlatformName()}.";
                            if ($reviewStats) {
                                $body .= " Imported {$reviewStats['imported']} reviews.";
                            }

                            Notification::make()
                                ->title('Game created and synced successfully')
                                ->body($body)
                                ->success()
                                ->duration(10000)
                                ->send();
                        } catch (Exception $e) {
                            Notification::make()
                                ->title('Failed to create and sync game')
                                ->body($e->getMessage())
                                ->danger()
                                ->duration(10000)
                                ->send();
                        }
                    }),

                Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->schema([
                        Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->maxLength(1000)
                            ->rows(3),
                    ])
                    ->visible(fn (AdditionRequest $record): bool => $record->isPending())
                    ->action(function (AdditionRequest $record, array $data): void {
                        $user = Auth::user();
                        if ($user instanceof User) {
                            $record->reject($user, $data['rejection_reason']);
                            Notification::make()
                                ->title('Request rejected successfully')
                                ->success()
                                ->send();
                        }
                    }),

                Action::make('sync_game')
                    ->label('Sync Game Data')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Sync Game Data')
                    ->modalDescription(fn (AdditionRequest $record): string =>
                        $record->game
                            ? "This will fetch the latest data from {$record->game->getPlatformName()} for \"{$record->game->name}\". This may take a few minutes."
                            : "No game linked to this request."
                    )
                    ->modalSubmitActionLabel('Sync Now')
                    ->visible(fn (AdditionRequest $record): bool =>
                        $record->game &&
                        ($record->game->platform === 'itch_io' || $record->game->platform === 'steam')
                    )
                    ->action(function (AdditionRequest $record): void {
                        if (!$record->game) {
                            Notification::make()
                                ->title('No game linked')
                                ->body('This request does not have a linked game.')
                                ->warning()
                                ->send();
                            return;
                        }

                        try {
                            $syncService = app(GameDataSyncService::class);
                            $syncService->loadFullDetails($record->game);
                            $record->game->save();

                            Notification::make()
                                ->title('Game synced successfully')
                                ->body("Data for \"{$record->game->name}\" has been updated from {$record->game->getPlatformName()}.")
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

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve_selected')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $user = Auth::user();
                            if ($user instanceof User) {
                                $count = 0;
                                foreach ($records as $record) {
                                    if ($record->isPending()) {
                                        $record->approve($user);
                                        $count++;
                                    }
                                }
                                Notification::make()
                                    ->title("Approved {$count} request(s)")
                                    ->success()
                                    ->send();
                            }
                        }),

                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Request Information')
                    ->schema(components: [
                        TextInput::make('game_url')
                            ->label('Game URL')
                            ->required()
                            ->url()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Select::make('platform')
                            ->options([
                                'itch_io' => 'itch.io',
                                'steam' => 'Steam',
                                'other' => 'Other',
                            ])
                            ->nullable()
                            ->label('Platform'),

                        Select::make('status')
                            ->options([
                                AdditionRequest::STATUS_PENDING => 'Pending',
                                AdditionRequest::STATUS_APPROVED => 'Approved',
                                AdditionRequest::STATUS_REJECTED => 'Rejected',
                            ])
                            ->required()
                            ->default(AdditionRequest::STATUS_PENDING),

                        Select::make('game_id')
                            ->label('Linked Game')
                            ->relationship(
                                name: 'game',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->orderBy('name')
                            )
                            ->getOptionLabelFromRecordUsing(function (Game $record): string {
                                $platformUrl = $record->getPrimaryUrl();
                                $urlDisplay = $platformUrl ? self::extractUrlIdentifier($platformUrl) : 'No URL';
                                return $record->name . ' (' . $urlDisplay . ')';
                            })
                            ->searchable(['name'])
                            ->preload()
                            ->nullable()
                            ->helperText('Select the game if this request has been approved and added to the site'),

                        Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->maxLength(1000)
                            ->rows(3)
                            ->visible(fn (Get $get) => $get('status') === AdditionRequest::STATUS_REJECTED)
                            ->required(fn (Get $get) => $get('status') === AdditionRequest::STATUS_REJECTED)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Review Information')
                    ->schema([
                        DateTimePicker::make('reviewed_at')
                            ->label('Reviewed At')
                            ->disabled()
                            ->dehydrated(false),

                        Select::make('reviewed_by')
                            ->label('Reviewed By')
                            ->relationship('reviewer', 'name')
                            ->disabled()
                            ->dehydrated(false),
                    ])->columns(2)
                    ->visible(fn (?AdditionRequest $record
                    ) => $record && ($record->reviewed_at || $record->status !== AdditionRequest::STATUS_PENDING)),
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
            'index' => ListAdditionRequests::route('/'),
            'edit' => EditAdditionRequest::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $pendingCount = static::getNavigationBadge();

        return $pendingCount > 0 ? 'warning' : null;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', AdditionRequest::STATUS_PENDING)->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * Extract a readable identifier from a game URL for display purposes.
     * Works with itch.io, Steam, and other platforms.
     */
    private static function extractUrlIdentifier(string $url): string
    {
        // Parse the URL to extract subdomain and path
        $parsed = parse_url($url);

        if (! $parsed || ! isset($parsed['host'])) {
            return $url; // Return original URL if parsing fails
        }

        $host = $parsed['host'];
        $path = $parsed['path'] ?? '';

        // Extract subdomain from itch.io URLs
        if (str_ends_with($host, '.itch.io')) {
            $subdomain = str_replace('.itch.io', '', $host);
            $gameSlug = trim($path, '/');

            if ($gameSlug) {
                return $subdomain . '/' . $gameSlug;
            }

            return $subdomain . '.itch.io';
        }

        // Extract Steam App ID from Steam URLs
        if (str_contains($host, 'steampowered.com') || str_contains($host, 'store.steampowered.com')) {
            if (preg_match('/\/app\/(\d+)/', $path, $matches)) {
                return 'Steam App ' . $matches[1];
            }
        }

        // For other URLs, return the host and path
        return $host . $path;
    }
}
