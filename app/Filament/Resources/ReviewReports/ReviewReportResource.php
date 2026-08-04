<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReviewReports;

use App\Filament\Resources\ReviewReports\Pages\ListReviewReports;
use App\Filament\Resources\ReviewReports\Pages\ViewReviewReport;
use App\Models\Rating;
use App\Models\ReviewReport;
use App\Services\HtmlSanitizerService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use UnitEnum;

class ReviewReportResource extends Resource
{
    protected static ?string $model = ReviewReport::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-flag';

    protected static string|UnitEnum|null $navigationGroup = 'User Feedback';

    protected static ?string $navigationLabel = 'Review Reports';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('reporter.name')
                    ->label('Reporter')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('rating.game.name')
                    ->label('Game')
                    ->searchable()
                    ->limit(30),

                TextColumn::make('reason')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ReviewReport::REASONS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'hate_speech', 'harassment' => 'danger',
                        'spam' => 'warning',
                        'spoilers' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('details')
                    ->limit(40)
                    ->placeholder('No details'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'dismissed' => 'gray',
                        'actioned' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('rating.review')
                    ->label('Review Text')
                    ->limit(50)
                    ->placeholder('No review text'),

                TextColumn::make('created_at')
                    ->label('Reported')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'dismissed' => 'Dismissed',
                        'actioned' => 'Actioned',
                    ])
                    ->default('pending'),

                SelectFilter::make('reason')
                    ->options(ReviewReport::REASONS),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('dismiss')
                    ->label('Dismiss')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->schema([
                        Textarea::make('admin_notes')
                            ->label('Notes')
                            ->placeholder('Why is this being dismissed?')
                            ->rows(2),
                    ])
                    ->visible(fn (ReviewReport $record): bool => $record->status === 'pending')
                    ->action(function (ReviewReport $record, array $data): void {
                        $record->update([
                            'status' => 'dismissed',
                            'reviewed_by' => Auth::id(),
                            'reviewed_at' => now(),
                            'admin_notes' => $data['admin_notes'] ?? null,
                        ]);
                        Notification::make()
                            ->title('Report dismissed')
                            ->success()
                            ->send();
                    }),

                Action::make('action_hide')
                    ->label('Hide Review')
                    ->icon('heroicon-o-eye-slash')
                    ->color('danger')
                    ->schema([
                        Textarea::make('admin_notes')
                            ->label('Notes')
                            ->placeholder('Why is this review being hidden?')
                            ->rows(2),
                    ])
                    ->visible(fn (ReviewReport $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalDescription('This will hide the reported review from public view.')
                    ->action(function (ReviewReport $record, array $data): void {
                        $record->update([
                            'status' => 'actioned',
                            'reviewed_by' => Auth::id(),
                            'reviewed_at' => now(),
                            'admin_notes' => $data['admin_notes'] ?? null,
                        ]);

                        if ($record->rating) {
                            $record->rating->update(['is_visible' => false]);
                        }

                        Notification::make()
                            ->title('Review hidden and report resolved')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('dismiss_all')
                        ->label('Dismiss Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->status === 'pending') {
                                    $record->update([
                                        'status' => 'dismissed',
                                        'reviewed_by' => Auth::id(),
                                        'reviewed_at' => now(),
                                    ]);
                                    $count++;
                                }
                            }
                            Notification::make()
                                ->title("Dismissed {$count} report(s)")
                                ->success()
                                ->send();
                        }),
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
                        TextInput::make('reason')
                            ->formatStateUsing(fn (string $state): string => ReviewReport::REASONS[$state] ?? $state)
                            ->disabled(),

                        TextInput::make('status')
                            ->disabled(),

                        Textarea::make('details')
                            ->disabled()
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Reported Review')
                    ->schema([
                        Placeholder::make('review_game')
                            ->label('Game')
                            ->content(fn (ReviewReport $record): string => $record->rating?->game?->name ?? 'Unknown'),

                        Placeholder::make('review_rating')
                            ->label('Rating')
                            ->content(fn (ReviewReport $record): string => $record->rating?->rating ? $record->rating->rating.'/5' : 'N/A'),

                        Placeholder::make('review_author')
                            ->label('Review Author')
                            ->content(fn (ReviewReport $record): string => $record->rating?->user?->name ?? $record->rating?->rater?->name ?? 'Unknown'),

                        Placeholder::make('review_visible')
                            ->label('Visible')
                            ->content(fn (ReviewReport $record): string => $record->rating?->is_visible ? 'Yes' : 'Hidden'),

                        Placeholder::make('review_text')
                            ->label('Review Text')
                            ->content(fn (ReviewReport $record): HtmlString => new HtmlString(
                                app(HtmlSanitizerService::class)->sanitizeReview($record->rating?->review) ?: 'No review text'
                            ))
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Other Reviews by This Author')
                    ->schema([
                        Placeholder::make('user_review_ban_status')
                            ->label('Moderation Status')
                            ->content(function (ReviewReport $record): string {
                                $user = $record->rating?->user;
                                $rater = $record->rating?->rater;

                                if ($user) {
                                    return $user->is_review_banned ? 'BANNED (FVN.li user)' : 'Active (FVN.li user)';
                                }

                                if ($rater) {
                                    $statuses = [];
                                    if ($rater->is_review_banned) {
                                        $statuses[] = 'BANNED';
                                    }
                                    if ($rater->is_suspicious) {
                                        $statuses[] = 'Flagged as suspicious';
                                    }

                                    return $statuses ? implode(', ', $statuses).' (external rater)' : 'Active (external rater)';
                                }

                                return 'Unknown author';
                            }),

                        Placeholder::make('user_other_reviews')
                            ->label('All Reviews')
                            ->content(function (ReviewReport $record): HtmlString {
                                $rating = $record->rating;
                                $user = $rating?->user;
                                $rater = $rating?->rater;

                                if ($user) {
                                    $reviews = Rating::where('user_id', $user->id)
                                        ->with('game:id,name,slug')
                                        ->orderBy('created_at', 'desc')
                                        ->get();
                                } elseif ($rater) {
                                    $reviews = Rating::where('rater_id', $rater->id)
                                        ->with('game:id,name,slug')
                                        ->orderBy('created_at', 'desc')
                                        ->get();
                                } else {
                                    return new HtmlString('<em>No author found for this review.</em>');
                                }

                                if ($reviews->isEmpty()) {
                                    return new HtmlString('<em>No reviews found.</em>');
                                }

                                $html = '<div class="space-y-3">';
                                foreach ($reviews as $review) {
                                    $gameName = e($review->game?->name ?? 'Unknown');
                                    $ratingText = $review->rating ? $review->rating.'/5' : 'N/A';
                                    $visible = $review->is_visible ? '<span class="text-green-600">Visible</span>' : '<span class="text-red-600">Hidden</span>';
                                    $excerpt = $review->review ? e(mb_substr(strip_tags($review->review), 0, 150)).'...' : '<em>No text</em>';
                                    $isCurrent = $review->id === $record->rating_id ? ' <span class="text-yellow-600 font-bold">(reported)</span>' : '';

                                    $html .= '<div class="p-2 rounded border border-gray-200 dark:border-gray-700">';
                                    $html .= "<div class=\"font-medium\">{$gameName}{$isCurrent} · {$ratingText} · {$visible}</div>";
                                    $html .= "<div class=\"text-sm text-gray-500 mt-1\">{$excerpt}</div>";
                                    $html .= '</div>';
                                }
                                $html .= '</div>';

                                return new HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Admin')
                    ->schema([
                        Textarea::make('admin_notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReviewReports::route('/'),
            'view' => ViewReviewReport::route('/{record}'),
        ];
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $count = static::getNavigationBadge();

        return $count > 0 ? 'danger' : null;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'pending')->count();

        return $count > 0 ? (string) $count : null;
    }
}
