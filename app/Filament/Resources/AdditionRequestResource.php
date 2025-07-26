<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AdditionRequestResource\Pages\EditAdditionRequest;
use App\Filament\Resources\AdditionRequestResource\Pages\ListAdditionRequests;
use App\Models\AdditionRequest;
use App\Models\Game;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class AdditionRequestResource extends Resource
{
    protected static ?string $model = AdditionRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Content Management';

    protected static ?string $recordTitleAttribute = 'itch_url';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Request Information')
                    ->schema(components: [
                        TextInput::make('itch_url')
                            ->label('Itch.io URL')
                            ->required()
                            ->url()
                            ->maxLength(255)
                            ->columnSpanFull(),

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
                            ->getOptionLabelFromRecordUsing(fn (Game $record): string => $record->name . ' (' . self::extractItchSubdomain($record->url) . ')'
                            )
                            ->searchable(['name'])
                            ->preload()
                            ->nullable()
                            ->helperText('Select the game if this request has been approved and added to the site'),

                        Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->maxLength(1000)
                            ->rows(3)
                            ->visible(fn (Forms\Get $get) => $get('status') === AdditionRequest::STATUS_REJECTED)
                            ->required(fn (Forms\Get $get) => $get('status') === AdditionRequest::STATUS_REJECTED)
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
                    ->visible(fn (?AdditionRequest $record) => $record && ($record->reviewed_at || $record->status !== AdditionRequest::STATUS_PENDING)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('itch_url')
                    ->label('Itch.io URL')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->tooltip(fn (AdditionRequest $record): string => $record->itch_url),

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

                        return $state . ' (' . self::extractItchSubdomain($record->game->url) . ')';
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
            ->actions([
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

                Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
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

                EditAction::make(),
            ])
            ->bulkActions([
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

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', AdditionRequest::STATUS_PENDING)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $pendingCount = static::getNavigationBadge();

        return $pendingCount > 0 ? 'warning' : null;
    }

    /**
     * Extract the itch.io subdomain and game slug from a URL for display purposes.
     */
    private static function extractItchSubdomain(string $url): string
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

        // For non-itch.io URLs, return the full host
        return $host . $path;
    }
}
