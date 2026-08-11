<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReviewReports\Pages;

use App\Filament\Resources\ReviewReports\ReviewReportResource;
use App\Models\Rating;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewReviewReport extends ViewRecord
{
    protected static string $resource = ReviewReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
                ->visible(fn (): bool => $this->record->status === 'pending')
                ->action(function (array $data): void {
                    $this->record->update([
                        'status' => 'dismissed',
                        'reviewed_by' => Auth::id(),
                        'reviewed_at' => now(),
                        'admin_notes' => $data['admin_notes'] ?? null,
                    ]);
                    Notification::make()
                        ->title('Report dismissed')
                        ->success()
                        ->send();
                    $this->refreshFormData(['status', 'reviewed_by', 'reviewed_at', 'admin_notes']);
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
                ->visible(fn (): bool => $this->record->status === 'pending')
                ->requiresConfirmation()
                ->modalDescription('This will hide the reported review from public view.')
                ->action(function (array $data): void {
                    $this->record->update([
                        'status' => 'actioned',
                        'reviewed_by' => Auth::id(),
                        'reviewed_at' => now(),
                        'admin_notes' => $data['admin_notes'] ?? null,
                    ]);

                    if ($this->record->rating) {
                        $this->record->rating->update(['is_visible' => false, 'is_moderation_hidden' => true]);
                    }

                    Notification::make()
                        ->title('Review hidden and report resolved')
                        ->success()
                        ->send();
                    $this->refreshFormData(['status', 'reviewed_by', 'reviewed_at', 'admin_notes']);
                }),

            Action::make('hide_all_author_reviews')
                ->label('Hide All Reviews by Author')
                ->icon('heroicon-o-eye-slash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription(function (): string {
                    $rating = $this->record->rating;
                    if ($rating?->user) {
                        return 'This will hide ALL reviews by this FVN.li user. Use "Ban User" to also prevent future reviews.';
                    }

                    return 'This will hide ALL reviews by this external rater. Use "Ban Rater" to also prevent future imports.';
                })
                ->visible(fn (): bool => $this->record->rating?->user_id !== null || $this->record->rating?->rater_id !== null)
                ->action(function (): void {
                    $rating = $this->record->rating;

                    if ($rating->user_id) {
                        $count = Rating::where('user_id', $rating->user_id)
                            ->where('is_visible', true)
                            ->update(['is_visible' => false, 'is_moderation_hidden' => true]);
                    } else {
                        $count = Rating::where('rater_id', $rating->rater_id)
                            ->where('is_visible', true)
                            ->update(['is_visible' => false, 'is_moderation_hidden' => true]);
                    }

                    Notification::make()
                        ->title("Hidden {$count} review(s) by this author")
                        ->success()
                        ->send();
                    $this->refreshFormData(['status']);
                }),

            Action::make('ban_user')
                ->label('Ban User from Reviewing')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('This will ban the user from submitting new reviews AND hide all their existing reviews.')
                ->visible(fn (): bool => $this->record->rating?->user !== null && ! $this->record->rating->user->is_review_banned)
                ->action(function (): void {
                    $user = $this->record->rating->user;
                    $user->update(['is_review_banned' => true]);

                    $count = Rating::where('user_id', $user->id)
                        ->where('is_visible', true)
                        ->update(['is_visible' => false, 'is_moderation_hidden' => true]);

                    if ($this->record->status === 'pending') {
                        $this->record->update([
                            'status' => 'actioned',
                            'reviewed_by' => Auth::id(),
                            'reviewed_at' => now(),
                            'admin_notes' => ($this->record->admin_notes ? $this->record->admin_notes."\n" : '').'User banned from reviewing.',
                        ]);
                    }

                    Notification::make()
                        ->title("User banned and {$count} review(s) hidden")
                        ->success()
                        ->send();
                    $this->refreshFormData(['status', 'reviewed_by', 'reviewed_at', 'admin_notes']);
                }),

            Action::make('ban_rater')
                ->label('Ban Rater from Reviewing')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('This will ban this external rater from having visible reviews. All existing reviews will be hidden and future imports will be auto-hidden.')
                ->visible(fn (): bool => $this->record->rating?->rater !== null
                    && $this->record->rating?->user_id === null
                    && ! $this->record->rating->rater->is_review_banned)
                ->action(function (): void {
                    $rater = $this->record->rating->rater;
                    $rater->update(['is_review_banned' => true]);

                    $count = Rating::where('rater_id', $rater->id)
                        ->where('is_visible', true)
                        ->update(['is_visible' => false, 'is_moderation_hidden' => true]);

                    if ($this->record->status === 'pending') {
                        $this->record->update([
                            'status' => 'actioned',
                            'reviewed_by' => Auth::id(),
                            'reviewed_at' => now(),
                            'admin_notes' => ($this->record->admin_notes ? $this->record->admin_notes."\n" : '').'Rater banned from reviewing.',
                        ]);
                    }

                    Notification::make()
                        ->title("Rater banned and {$count} review(s) hidden")
                        ->success()
                        ->send();
                    $this->refreshFormData(['status', 'reviewed_by', 'reviewed_at', 'admin_notes']);
                }),

            Action::make('flag_rater')
                ->label('Flag Rater as Suspicious')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('This will flag this external rater as a suspected bot account. This does NOT ban them — use "Ban Rater" to hide their reviews.')
                ->visible(fn (): bool => $this->record->rating?->rater !== null
                    && $this->record->rating?->user_id === null
                    && ! $this->record->rating->rater->is_suspicious)
                ->action(function (): void {
                    $rater = $this->record->rating->rater;
                    $rater->update([
                        'is_suspicious' => true,
                        'suspicion_reason' => 'Flagged via review report',
                        'marked_suspicious_at' => now(),
                    ]);

                    Notification::make()
                        ->title('Rater flagged as suspicious')
                        ->success()
                        ->send();
                    $this->refreshFormData(['status']);
                }),

            Action::make('unban_user')
                ->label('Unban User')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('This will allow the user to submit reviews again. Their previously hidden reviews will NOT be restored.')
                ->visible(fn (): bool => $this->record->rating?->user !== null && $this->record->rating->user->is_review_banned)
                ->action(function (): void {
                    $this->record->rating->user->update(['is_review_banned' => false]);

                    Notification::make()
                        ->title('User unbanned — they can submit reviews again')
                        ->success()
                        ->send();
                }),

            Action::make('unban_rater')
                ->label('Unban Rater')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('This will allow future imports of this rater\'s reviews to be visible again. Previously hidden reviews will NOT be restored.')
                ->visible(fn (): bool => $this->record->rating?->rater !== null
                    && $this->record->rating?->user_id === null
                    && $this->record->rating->rater->is_review_banned)
                ->action(function (): void {
                    $this->record->rating->rater->update(['is_review_banned' => false]);

                    Notification::make()
                        ->title('Rater unbanned — future imports will be visible')
                        ->success()
                        ->send();
                }),

            Action::make('unflag_rater')
                ->label('Unflag Rater')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('This will remove the suspicious flag from this rater.')
                ->visible(fn (): bool => $this->record->rating?->rater !== null
                    && $this->record->rating?->user_id === null
                    && $this->record->rating->rater->is_suspicious)
                ->action(function (): void {
                    $this->record->rating->rater->update([
                        'is_suspicious' => false,
                        'suspicion_reason' => null,
                        'marked_suspicious_at' => null,
                    ]);

                    Notification::make()
                        ->title('Rater unflagged')
                        ->success()
                        ->send();
                }),

            DeleteAction::make(),
        ];
    }
}
