<?php

declare(strict_types=1);

namespace App\Filament\Resources\BugReports;

use App\Filament\Resources\BugReports\Pages\EditBugReport;
use App\Filament\Resources\BugReports\Pages\ListBugReports;
use App\Filament\Resources\BugReports\Pages\ViewBugReport;
use App\Filament\Resources\BugReports\RelationManagers\CommentsRelationManager;
use App\Models\BugReport;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class BugReportResource extends Resource
{
    protected static ?string $model = BugReport::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bug-ant';

    protected static string|UnitEnum|null $navigationGroup = 'User Feedback';

    protected static ?string $recordTitleAttribute = 'page_url';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Reporter')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('page_url')
                    ->label('Page')
                    ->limit(40)
                    ->tooltip(fn (BugReport $record): string => $record->page_url)
                    ->searchable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->tooltip(fn (BugReport $record): string => $record->description),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        BugReport::STATUS_OPEN => 'warning',
                        BugReport::STATUS_IN_PROGRESS => 'info',
                        BugReport::STATUS_RESOLVED => 'success',
                        BugReport::STATUS_WONT_FIX => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => BugReport::getStatuses()[$state] ?? $state),

                TextColumn::make('is_closed')
                    ->label('User Closed')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'gray' : 'success')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Closed' : 'Active'),

                TextColumn::make('resolver.name')
                    ->label('Resolved By')
                    ->placeholder('Not resolved'),

                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable()
                    ->since(),

                TextColumn::make('resolved_at')
                    ->label('Resolved')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->placeholder('Not resolved'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(BugReport::getStatuses()),

                SelectFilter::make('user_id')
                    ->label('Reporter')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('mark_in_progress')
                    ->label('In Progress')
                    ->icon('heroicon-o-play')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (BugReport $record): bool => $record->status === BugReport::STATUS_OPEN)
                    ->action(function (BugReport $record): void {
                        $record->update(['status' => BugReport::STATUS_IN_PROGRESS]);
                        Notification::make()
                            ->title('Bug report marked as in progress')
                            ->success()
                            ->send();
                    }),

                Action::make('resolve')
                    ->label('Resolve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->schema([
                        Textarea::make('admin_notes')
                            ->label('Resolution Notes')
                            ->placeholder('Describe how the issue was resolved...')
                            ->rows(3),
                    ])
                    ->visible(fn (BugReport $record): bool => ! $record->isClosed())
                    ->action(function (BugReport $record, array $data): void {
                        $user = Auth::user();
                        if ($user instanceof User) {
                            $record->markAsResolved($user, $data['admin_notes'] ?? null);
                            Notification::make()
                                ->title('Bug report resolved')
                                ->success()
                                ->send();
                        }
                    }),

                Action::make('close')
                    ->label('Close for User')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('This will mark the report as closed on behalf of the user. They will no longer see it on their dashboard.')
                    ->visible(fn (BugReport $record): bool => ! $record->is_closed)
                    ->action(function (BugReport $record): void {
                        $record->update(['is_closed' => true]);
                        Notification::make()
                            ->title('Bug report closed for user')
                            ->success()
                            ->send();
                    }),

                Action::make('wont_fix')
                    ->label("Won't Fix")
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->schema([
                        Textarea::make('admin_notes')
                            ->label('Reason')
                            ->placeholder("Explain why this won't be fixed...")
                            ->required()
                            ->rows(3),
                    ])
                    ->visible(fn (BugReport $record): bool => ! $record->isClosed())
                    ->action(function (BugReport $record, array $data): void {
                        $user = Auth::user();
                        if ($user instanceof User) {
                            $record->update([
                                'status' => BugReport::STATUS_WONT_FIX,
                                'resolved_by' => $user->id,
                                'resolved_at' => now(),
                                'admin_notes' => $data['admin_notes'],
                            ]);
                            Notification::make()
                                ->title("Bug report marked as won't fix")
                                ->success()
                                ->send();
                        }
                    }),

                Action::make('reopen')
                    ->label('Reopen for User')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('This will reopen the report so the user sees it on their dashboard again.')
                    ->visible(fn (BugReport $record): bool => $record->is_closed)
                    ->action(function (BugReport $record): void {
                        $record->update(['is_closed' => false]);
                        Notification::make()
                            ->title('Bug report reopened for user')
                            ->success()
                            ->send();
                    }),

                Action::make('reset_status')
                    ->label('Reset Status')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('This will reset the status to Open and clear resolution info.')
                    ->visible(fn (BugReport $record): bool => $record->isTerminal())
                    ->action(function (BugReport $record): void {
                        $record->update([
                            'status' => BugReport::STATUS_OPEN,
                            'resolved_by' => null,
                            'resolved_at' => null,
                        ]);
                        Notification::make()
                            ->title('Bug report status reset')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('mark_resolved')
                        ->label('Mark as Resolved')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $user = Auth::user();
                            if ($user instanceof User) {
                                $count = 0;
                                foreach ($records as $record) {
                                    if (! $record->isClosed()) {
                                        $record->markAsResolved($user);
                                        $count++;
                                    }
                                }
                                Notification::make()
                                    ->title("Resolved {$count} bug report(s)")
                                    ->success()
                                    ->send();
                            }
                        }),

                    BulkAction::make('mark_closed')
                        ->label('Close for Users')
                        ->icon('heroicon-o-x-circle')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalDescription('This will mark selected reports as closed. Users will no longer see them on their dashboards.')
                        ->action(function (Collection $records): void {
                            $count = 0;
                            foreach ($records as $record) {
                                if (! $record->is_closed) {
                                    $record->update(['is_closed' => true]);
                                    $count++;
                                }
                            }
                            Notification::make()
                                ->title("Closed {$count} bug report(s) for users")
                                ->success()
                                ->send();
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
                Section::make('Report Details')
                    ->schema([
                        Select::make('user_id')
                            ->label('Reporter')
                            ->relationship('user', 'name')
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('page_url')
                            ->label('Page URL')
                            ->disabled()
                            ->columnSpanFull(),

                        TextInput::make('page_title')
                            ->label('Page Title')
                            ->disabled(),

                        TextInput::make('user_agent')
                            ->label('User Agent')
                            ->disabled()
                            ->columnSpanFull(),

                        KeyValue::make('request_parameters')
                            ->label('Request Parameters')
                            ->disabled()
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->label('Description')
                            ->disabled()
                            ->rows(5)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Status Management')
                    ->schema([
                        Select::make('status')
                            ->options(BugReport::getStatuses())
                            ->required(),

                        Toggle::make('is_closed')
                            ->label('Closed for User')
                            ->helperText('When enabled, the user will not see this report on their dashboard.'),

                        Textarea::make('admin_notes')
                            ->label('Admin Notes')
                            ->placeholder('Internal notes about this report...')
                            ->rows(3)
                            ->columnSpanFull(),

                        Select::make('resolved_by')
                            ->label('Resolved By')
                            ->relationship('resolver', 'name')
                            ->disabled()
                            ->dehydrated(false),

                        DateTimePicker::make('resolved_at')
                            ->label('Resolved At')
                            ->disabled()
                            ->dehydrated(false),
                    ])->columns(2),

                Section::make('Timestamps')
                    ->schema([
                        DateTimePicker::make('created_at')
                            ->label('Submitted At')
                            ->disabled()
                            ->dehydrated(false),

                        DateTimePicker::make('updated_at')
                            ->label('Last Updated')
                            ->disabled()
                            ->dehydrated(false),
                    ])->columns(2)
                    ->collapsed(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            CommentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBugReports::route('/'),
            'view' => ViewBugReport::route('/{record}'),
            'edit' => EditBugReport::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $openCount = static::getNavigationBadge();

        return $openCount > 0 ? 'warning' : null;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', BugReport::STATUS_OPEN)->count();

        return $count > 0 ? (string) $count : null;
    }
}
