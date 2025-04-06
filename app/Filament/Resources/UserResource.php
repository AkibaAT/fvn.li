<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Models\User;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'User Management';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('User Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('avatar')
                            ->label('Avatar URL')
                            ->maxLength(255)
                            ->helperText('Full URL to the avatar image from social login'),
                        Toggle::make('is_admin')
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable()
                    ->searchable(),
                ImageColumn::make('avatar')
                    ->label('Avatar')
                    ->circular()
                    ->extraImgAttributes(['referrerpolicy' => 'no-referrer'])
                    ->defaultImageUrl(function ($record) {
                        return 'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&color=FFFFFF&background=6366F1';
                    }),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                IconColumn::make('is_admin')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('socialAccounts.provider_name')
                    ->badge()
                    ->color('primary')
                    ->label('Connected Accounts')
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->listWithLineBreaks()
                    ->limitList(3),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_admin'),
                SelectFilter::make('social_provider')
                    ->label('Social Provider')
                    ->options([
                        'discord' => 'Discord',
                        'google' => 'Google',
                        'telegram' => 'Telegram',
                        'steam' => 'Steam',
                        'itchio' => 'Itch.io',
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $data['value'] ?
                            $query->whereHas('socialAccounts', fn ($q) => $q->where('provider_name', $data['value'])) :
                            $query;
                    }),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('makeAdmin')
                        ->label('Make Admin')
                        ->icon('heroicon-o-shield-check')
                        ->action(function (Collection $records): void {
                            foreach ($records as $record) {
                                $record->is_admin = true;
                                $record->save();
                            }
                        }),
                    BulkAction::make('removeAdmin')
                        ->label('Remove Admin')
                        ->icon('heroicon-o-shield-exclamation')
                        ->action(function (Collection $records): void {
                            foreach ($records as $record) {
                                $record->is_admin = false;
                                $record->save();
                            }
                        }),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('User Information')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('email'),
                        ImageEntry::make('avatar')
                            ->label('Avatar')
                            ->circular()
                            ->extraImgAttributes(['referrerpolicy' => 'no-referrer'])
                            ->defaultImageUrl(function ($record) {
                                return 'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&color=FFFFFF&background=6366F1';
                            }),
                        IconEntry::make('is_admin')
                            ->boolean(),
                        TextEntry::make('created_at')
                            ->dateTime(),
                    ])->columns(2),

                InfolistSection::make('Connected Social Accounts')
                    ->schema([
                        RepeatableEntry::make('socialAccounts')
                            ->schema([
                                TextEntry::make('provider_name')
                                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'discord' => 'purple',
                                        'google' => 'danger',
                                        'telegram' => 'info',
                                        'steam' => 'gray',
                                        'itchio' => 'warning',
                                        default => 'primary',
                                    }),
                                TextEntry::make('provider_id')
                                    ->label('Provider ID'),
                                TextEntry::make('created_at')
                                    ->dateTime()
                                    ->label('Connected On'),
                            ])
                            ->columns(3),
                    ]),
            ]);
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
