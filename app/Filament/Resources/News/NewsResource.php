<?php

declare(strict_types=1);

namespace App\Filament\Resources\News;

use App\Filament\Resources\News\Pages\CreateNews;
use App\Filament\Resources\News\Pages\EditNews;
use App\Filament\Resources\News\Pages\ListNews;
use App\Models\News;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class NewsResource extends Resource
{
    protected static ?string $model = News::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static string|UnitEnum|null $navigationGroup = 'Content Management';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationLabel = 'News & Announcements';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('News Information')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Auto-generated from title, but can be customized'),
                        Select::make('type')
                            ->required()
                            ->options([
                                News::TYPE_ANNOUNCEMENT => 'Announcement',
                                News::TYPE_UPDATE => 'Update',
                                News::TYPE_MAINTENANCE => 'Maintenance',
                                News::TYPE_INCIDENT => 'Incident',
                            ])
                            ->default(News::TYPE_ANNOUNCEMENT)
                            ->helperText('Choose the type of news item'),
                    ])->columns(2),

                Section::make('Content')
                    ->schema([
                        RichEditor::make('content')
                            ->required()
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'link',
                                'bulletList',
                                'orderedList',
                                'h2',
                                'h3',
                                'blockquote',
                                'codeBlock',
                            ])
                            ->helperText('Write the news content. HTML formatting is supported.'),
                    ]),

                Section::make('Publishing')
                    ->schema([
                        Toggle::make('is_published')
                            ->label('Published')
                            ->helperText('When enabled, this news item will be visible to users')
                            ->default(false),
                        DateTimePicker::make('published_at')
                            ->label('Publication Date')
                            ->helperText('Leave empty to use current date/time when publishing')
                            ->nullable(),
                        Select::make('author_id')
                            ->relationship('author', 'name')
                            ->required()
                            ->default(fn () => auth()->id())
                            ->helperText('The user who authored this news item'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        News::TYPE_ANNOUNCEMENT => 'info',
                        News::TYPE_UPDATE => 'success',
                        News::TYPE_MAINTENANCE => 'warning',
                        News::TYPE_INCIDENT => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->sortable(),
                IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('published_at')
                    ->label('Published')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('author.name')
                    ->label('Author')
                    ->sortable()
                    ->toggleable(),
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
                TernaryFilter::make('is_published')
                    ->label('Published')
                    ->placeholder('All')
                    ->trueLabel('Published only')
                    ->falseLabel('Unpublished only'),
                SelectFilter::make('type')
                    ->options([
                        News::TYPE_ANNOUNCEMENT => 'Announcement',
                        News::TYPE_UPDATE => 'Update',
                        News::TYPE_MAINTENANCE => 'Maintenance',
                        News::TYPE_INCIDENT => 'Incident',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('published_at', 'desc');
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
            'index' => ListNews::route('/'),
            'create' => CreateNews::route('/create'),
            'edit' => EditNews::route('/{record}/edit'),
        ];
    }
}

