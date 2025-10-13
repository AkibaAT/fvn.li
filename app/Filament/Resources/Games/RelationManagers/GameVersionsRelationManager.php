<?php

declare(strict_types=1);

namespace App\Filament\Resources\Games\RelationManagers;

use App\Models\GameVersion;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class GameVersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'gameVersions';

    protected static ?string $recordTitleAttribute = 'version';

    protected static ?string $title = 'Game Versions';

    /**
     * @throws Exception
     */
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('version')
            ->heading('Game Versions')
            ->columns([
                TextColumn::make('version')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
                IconColumn::make('is_latest')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('devlog')
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if ($state === null || strlen($state) <= 30) {
                            return null;
                        }

                        return $state;
                    }),
                IconColumn::make('is_windows')
                    ->boolean()
                    ->label('Win'),
                IconColumn::make('is_linux')
                    ->boolean()
                    ->label('Linux'),
                IconColumn::make('is_mac')
                    ->boolean()
                    ->label('Mac'),
                IconColumn::make('is_android')
                    ->boolean()
                    ->label('Android'),
                IconColumn::make('is_web')
                    ->boolean()
                    ->label('Web'),
            ])
            ->filters([
                TernaryFilter::make('is_latest'),
                TernaryFilter::make('is_windows'),
                TernaryFilter::make('is_linux'),
                TernaryFilter::make('is_mac'),
                TernaryFilter::make('is_android'),
                TernaryFilter::make('is_web'),
            ])
            ->headerActions([
                Action::make('createVersion')
                    ->label('Create New Version')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->url(fn () => route('filament.admin.resources.game-versions.create',
                        ['game_id' => $this->ownerRecord->id])),
                CreateAction::make(),
                Action::make('uploadJson')
                    ->label('Upload JSON')
                    ->icon('heroicon-o-document-plus')
                    ->schema([
                        FileUpload::make('json_file')
                            ->label('Version JSON File')
                            ->acceptedFileTypes(['application/json'])
                            ->required()
                            ->disk('local')
                            ->directory('temp/version-uploads'),
                    ])
                    ->action(function (array $data, RelationManager $livewire): void {
                        $filePath = Storage::disk('local')->path($data['json_file']);
                        $jsonData = json_decode(file_get_contents($filePath), true);

                        if (! $jsonData) {
                            // Handle invalid JSON
                            throw new Exception('Invalid JSON file');
                        }

                        // Get the game ID from the relation manager
                        $gameId = $livewire->getOwnerRecord()->id;

                        // Create a new version from the JSON data
                        $version = new GameVersion([
                            'game_id' => $gameId,
                            'version' => $jsonData['version'] ?? 'Imported',
                            'published_at' => $jsonData['published_at'] ?? now(),
                            'is_windows' => $jsonData['is_windows'] ?? false,
                            'is_linux' => $jsonData['is_linux'] ?? false,
                            'is_mac' => $jsonData['is_mac'] ?? false,
                            'is_android' => $jsonData['is_android'] ?? false,
                            'is_web' => $jsonData['is_web'] ?? false,
                        ]);

                        $version->save();

                        // Process character stats if available
                        // WARNING: This bypasses legacy data protection - only use for importing
                        // new versions or when you're certain the data is not legacy
                        if (isset($jsonData['character_stats']) && is_array($jsonData['character_stats'])) {
                            foreach ($jsonData['character_stats'] as $stat) {
                                $version->characterStats()->create([
                                    'character_id' => $stat['character_id'] ?? null,
                                    'iso_code' => $stat['iso_code'] ?? 'eng',
                                    'blocks' => $stat['blocks'] ?? 0,
                                    'words' => $stat['words'] ?? 0,
                                ]);
                            }
                        }

                        // Process language stats if available
                        if (isset($jsonData['language_stats']) && is_array($jsonData['language_stats'])) {
                            foreach ($jsonData['language_stats'] as $stat) {
                                $version->languageStats()->create([
                                    'iso_code' => $stat['iso_code'] ?? 'eng',
                                    'blocks' => $stat['blocks'] ?? 0,
                                    'words' => $stat['words'] ?? 0,
                                ]);
                            }
                        }

                        // Process supported languages if available
                        if (isset($jsonData['supported_languages']) && is_array($jsonData['supported_languages'])) {
                            foreach ($jsonData['supported_languages'] as $lang) {
                                $version->addSupportedLanguage(
                                    $lang['iso_code'] ?? 'eng',
                                    $lang['is_available'] ?? true
                                );
                            }
                        }

                        // Clean up the uploaded file
                        Storage::disk('local')->delete($data['json_file']);

                        // Show a success notification
                        Notification::make()
                            ->title('Version created')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View Details')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => route('filament.admin.resources.game-versions.edit', $record))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('markAsLatest')
                        ->label('Mark as Latest')
                        ->icon('heroicon-o-star')
                        ->action(function ($records) {
                            // First, unmark all versions for this game
                            $gameId = $records->first()->game_id;
                            $this->getRelationship()->where('game_id', $gameId)->update(['is_latest' => false]);

                            // Then mark the selected version as latest
                            $records->first()->update(['is_latest' => true]);
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('published_at', 'desc');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('version')
                    ->required()
                    ->maxLength(20),
                DateTimePicker::make('published_at')
                    ->required(),
                TextInput::make('devlog')
                    ->maxLength(250),
                Toggle::make('is_latest')
                    ->required(),
                Section::make('Platform Support')
                    ->schema([
                        Toggle::make('is_windows')
                            ->required(),
                        Toggle::make('is_linux')
                            ->required(),
                        Toggle::make('is_mac')
                            ->required(),
                        Toggle::make('is_android')
                            ->required(),
                        Toggle::make('is_web')
                            ->required(),
                    ])->columns(3),
            ]);
    }
}
