<?php

declare(strict_types=1);

namespace App\Filament\Resources\GameVersionResource\RelationManagers;

use App\Services\HelperService;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class FileCategoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'fileCategories';

    protected static ?string $recordTitleAttribute = 'category';

    protected static ?string $title = 'File Statistics';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Read-only view, no form fields needed
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category')
                    ->label('Category')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->sortable(),
                TextColumn::make('total_count')
                    ->label('File Count')
                    ->numeric()
                    ->formatStateUsing(fn (int $state): string => number_format($state))
                    ->sortable(),
                TextColumn::make('total_size')
                    ->label('Total Size')
                    ->formatStateUsing(fn (int $state): string => HelperService::formatBytes($state))
                    ->sortable(),
            ])
            ->filters([
                // No filters needed for this simple view
            ])
            ->actions([
                ViewAction::make()
                    ->label('View File Types')
                    ->icon('heroicon-o-document-text')
                    ->url(fn (Model $record): string => route('filament.admin.resources.version-file-categories.view', [
                        'record' => $record->id,
                    ]))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('category')
            ->paginated([10, 25, 50]);
    }
}
