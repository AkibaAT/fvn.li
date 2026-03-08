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
                        $this->record->rating->update(['is_visible' => false]);
                    }

                    Notification::make()
                        ->title('Review hidden and report resolved')
                        ->success()
                        ->send();
                    $this->refreshFormData(['status', 'reviewed_by', 'reviewed_at', 'admin_notes']);
                }),

            Action::make('hide_all_user_reviews')
                ->label('Hide All User Reviews')
                ->icon('heroicon-o-eye-slash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('This will hide ALL reviews by this user. This does not prevent them from submitting new reviews — use "Ban User" for that.')
                ->visible(fn (): bool => $this->record->rating?->user_id !== null)
                ->action(function (): void {
                    $userId = $this->record->rating->user_id;
                    $count = Rating::where('user_id', $userId)
                        ->where('is_visible', true)
                        ->update(['is_visible' => false]);

                    Notification::make()
                        ->title("Hidden {$count} review(s) by this user")
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
                        ->update(['is_visible' => false]);

                    // Also mark this report as actioned
                    if ($this->record->status === 'pending') {
                        $this->record->update([
                            'status' => 'actioned',
                            'reviewed_by' => Auth::id(),
                            'reviewed_at' => now(),
                            'admin_notes' => ($this->record->admin_notes ? $this->record->admin_notes . "\n" : '') . 'User banned from reviewing.',
                        ]);
                    }

                    Notification::make()
                        ->title("User banned and {$count} review(s) hidden")
                        ->success()
                        ->send();
                    $this->refreshFormData(['status', 'reviewed_by', 'reviewed_at', 'admin_notes']);
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

            DeleteAction::make(),
        ];
    }
}
