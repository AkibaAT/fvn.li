<?php

declare(strict_types=1);

namespace App\Filament\Resources\BugReports\RelationManagers;

use App\Models\BugReportComment;
use App\Models\User;
use App\Notifications\BugReportAdminReplyNotification;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = 'Conversation';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Conversation')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Author')
                    ->sortable(),

                IconColumn::make('is_from_admin')
                    ->label('Staff')
                    ->boolean()
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-user'),

                TextColumn::make('message')
                    ->label('Message')
                    ->limit(80)
                    ->wrap()
                    ->tooltip(fn (BugReportComment $record): string => $record->message),

                IconColumn::make('is_read')
                    ->label('Read')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Sent')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Reply')
                    ->form([
                        Textarea::make('message')
                            ->label('Message')
                            ->required()
                            ->minLength(5)
                            ->maxLength(2000)
                            ->rows(4)
                            ->placeholder('Type your reply to the user...'),
                        Hidden::make('user_id'),
                        Hidden::make('is_from_admin'),
                        Hidden::make('is_read'),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = Auth::id();
                        $data['is_from_admin'] = true;
                        $data['is_read'] = false; // User hasn't read it yet

                        return $data;
                    })
                    ->after(function (BugReportComment $record): void {
                        // Get the bug report
                        $bugReport = $record->bugReport;

                        // Send notification to the user who submitted the bug report
                        $reporter = $bugReport->user;
                        if ($reporter && $reporter->id !== Auth::id()) {
                            $reporter->notify(new BugReportAdminReplyNotification($bugReport, $record));
                        }

                        Notification::make()
                            ->title('Reply sent')
                            ->body('The user will be notified.')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->visible(fn (BugReportComment $record): bool => $record->is_from_admin && $record->user_id === Auth::id()),
            ])
            ->defaultSort('created_at', 'asc');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('message')
                    ->label('Message')
                    ->required()
                    ->rows(4),
            ]);
    }
}
