<?php

declare(strict_types=1);

namespace App\Filament\Resources\GameVersions;

use App\Filament\Resources\GameVersions\Pages\CreateGameVersion;
use App\Filament\Resources\GameVersions\Pages\EditGameVersion;
use App\Filament\Resources\GameVersions\Pages\ListGameVersions;
use App\Filament\Resources\GameVersions\Pages\ViewGameVersion;
use App\Filament\Resources\GameVersions\RelationManagers\CharacterStatsRelationManager;
use App\Filament\Resources\GameVersions\RelationManagers\FileCategoriesRelationManager;
use App\Models\GameVersion;
use App\Models\Language;
use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use UnitEnum;

class GameVersionResource extends Resource
{
    protected static ?string $model = GameVersion::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|UnitEnum|null $navigationGroup = 'Content Management';

    protected static ?string $recordTitleAttribute = 'version';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Version Information')
                    ->schema([
                        Select::make('game_id')
                            ->relationship('game', 'name')
                            ->required()
                            ->searchable()
                            ->default(fn () => request()->input('game_id')),
                        TextInput::make('version')
                            ->required()
                            ->maxLength(20),
                        DateTimePicker::make('published_at')
                            ->required(),
                        TextInput::make('devlog')
                            ->maxLength(250),
                        Toggle::make('is_latest')
                            ->required(),
                    ])->columns(2),

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

                Section::make('Ratings')
                    ->schema([
                        TextInput::make('rating')
                            ->numeric()
                            ->step(0.01)
                            ->maxValue(5),
                        TextInput::make('rating_count')
                            ->numeric()
                            ->step(1),
                    ])->columns(2),

                Section::make('Supported Languages')
                    ->schema([
                        Repeater::make('supported_languages')
                            ->schema([
                                Select::make('iso_code')
                                    ->label('Language')
                                    ->options(function () {
                                        return Language::orderBy('ref_name')
                                            ->pluck('ref_name', 'id')
                                            ->toArray();
                                    })
                                    ->searchable()
                                    ->required(),
                                Toggle::make('is_available')
                                    ->label('Available to users')
                                    ->default(true)
                                    ->helperText('If disabled, this language will not be shown to users'),
                            ])
                            ->columns(2)
                            ->itemLabel(function (array $state): ?string {
                                if (empty($state['iso_code'])) {
                                    return null;
                                }

                                $language = Language::find($state['iso_code']);

                                return $language ? $language->ref_name : $state['iso_code'];
                            })
                            ->addActionLabel('Add Language')
                            ->reorderableWithButtons()
                            ->collapsible(),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Version Information')
                    ->schema([
                        TextEntry::make('game.name')
                            ->label('Game'),
                        TextEntry::make('version'),
                        TextEntry::make('published_at')
                            ->dateTime(),
                        TextEntry::make('devlog'),
                        TextEntry::make('is_latest')
                            ->badge()
                            ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                    ])->columns(2),

                Section::make('Platform Support')
                    ->schema([
                        TextEntry::make('is_windows')
                            ->badge()
                            ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                        TextEntry::make('is_linux')
                            ->badge()
                            ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                        TextEntry::make('is_mac')
                            ->badge()
                            ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                        TextEntry::make('is_android')
                            ->badge()
                            ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                        TextEntry::make('is_web')
                            ->badge()
                            ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                    ])->columns(3),

                Section::make('Ratings')
                    ->schema([
                        TextEntry::make('rating')
                            ->numeric(2),
                        TextEntry::make('rating_count')
                            ->numeric(),
                    ])->columns(2),

                Section::make('Supported Languages')
                    ->schema([
                        TextEntry::make('supported_languages')
                            ->label('Languages')
                            ->getStateUsing(function (GameVersion $record) {
                                // Get the supported languages directly from the database
                                $languages = $record->supportedLanguages()
                                    ->with('language')
                                    ->get()
                                    ->map(function ($supportedLanguage) {
                                        $language = Language::find($supportedLanguage->iso_code);

                                        return [
                                            'name' => $language ? $language->ref_name : $supportedLanguage->iso_code,
                                            'is_available' => $supportedLanguage->is_available,
                                        ];
                                    })
                                    ->toArray();

                                if (empty($languages)) {
                                    return 'No languages configured';
                                }

                                $result = [];
                                foreach ($languages as $language) {
                                    $status = $language['is_available'] ? 'Available' : 'Not Available';
                                    $result[] = $language['name'] . ' - ' . $status;
                                }

                                return implode('<br>', $result);
                            })
                            ->html(),
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
                TextColumn::make('game.name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('version')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
                IconColumn::make('is_latest')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('rating')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rating_count')
                    ->numeric()
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
                TernaryFilter::make('is_latest'),
                TernaryFilter::make('is_windows'),
                TernaryFilter::make('is_linux'),
                TernaryFilter::make('is_mac'),
                TernaryFilter::make('is_android'),
                TernaryFilter::make('is_web'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('exportJson')
                    ->label('Export JSON')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(function (GameVersion $record): void {
                        // Prepare the version data for export
                        $data = [
                            'version' => $record->version,
                            'published_at' => $record->published_at->toIso8601String(),
                            'is_windows' => $record->is_windows,
                            'is_linux' => $record->is_linux,
                            'is_mac' => $record->is_mac,
                            'is_android' => $record->is_android,
                            'is_web' => $record->is_web,
                            'rating' => $record->rating,
                            'rating_count' => $record->rating_count,
                            'devlog' => $record->devlog,
                        ];

                        // Add character stats
                        $characterStats = [];
                        foreach ($record->characterStats as $stat) {
                            $characterStats[] = [
                                'character_id' => $stat->character_id,
                                'iso_code' => $stat->iso_code,
                                'blocks' => $stat->blocks,
                                'words' => $stat->words,
                            ];
                        }
                        $data['character_stats'] = $characterStats;

                        // Add language stats
                        $languageStats = [];
                        foreach ($record->languageStats as $stat) {
                            $languageStats[] = [
                                'iso_code' => $stat->iso_code,
                                'blocks' => $stat->blocks,
                                'words' => $stat->words,
                            ];
                        }
                        $data['language_stats'] = $languageStats;

                        // Add supported languages
                        $supportedLanguages = [];
                        foreach ($record->supportedLanguages as $lang) {
                            $supportedLanguages[] = [
                                'iso_code' => $lang->iso_code,
                                'is_available' => $lang->is_available,
                            ];
                        }
                        $data['supported_languages'] = $supportedLanguages;

                        // Generate a filename
                        $filename = 'version_' . $record->id . '_' . $record->version . '.json';

                        // Create a response with the JSON data
                        $response = response()->streamDownload(
                            function () use ($data) {
                                echo json_encode($data, JSON_PRETTY_PRINT);
                            },
                            $filename,
                            [
                                'Content-Type' => 'application/json',
                                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                            ]
                        );

                        $response->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('markAsLatest')
                        ->label('Mark as Latest')
                        ->icon('heroicon-o-star')
                        ->action(function (Collection $records): void {
                            // First, unmark all versions for the affected games
                            $gameIds = $records->pluck('game_id')->unique();
                            GameVersion::whereIn('game_id', $gameIds)->update(['is_latest' => false]);

                            // Then mark the selected versions as latest
                            foreach ($records as $record) {
                                $record->is_latest = true;
                                $record->save();
                            }
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            CharacterStatsRelationManager::class,
            FileCategoriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGameVersions::route('/'),
            'create' => CreateGameVersion::route('/create'),
            'view' => ViewGameVersion::route('/{record}'),
            'edit' => EditGameVersion::route('/{record}/edit'),
        ];
    }
}
