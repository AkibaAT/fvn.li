<?php

declare(strict_types=1);

namespace App\Filament\Resources\VersionFileCategoryResource\Pages;

use App\Filament\Resources\VersionFileCategoryResource;
use App\Models\VersionFileCategory;
use App\Services\HelperService;
use Filament\Resources\Pages\ViewRecord;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ViewVersionFileCategory extends ViewRecord implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = VersionFileCategoryResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                $this->getTableQuery()
            )
            ->columns([
                TextColumn::make('extension')
                    ->label('File Extension')
                    ->sortable(),
                TextColumn::make('count')
                    ->label('Count')
                    ->numeric()
                    ->formatStateUsing(fn (int $state): string => number_format($state))
                    ->sortable(),
                TextColumn::make('size')
                    ->label('Size')
                    ->formatStateUsing(fn (int $state): string => HelperService::formatBytes($state))
                    ->sortable(),
            ])
            ->defaultSort('extension')
            ->paginated([10, 25, 50, 100]);
    }

    protected function getTableQuery(): Builder
    {
        /** @var VersionFileCategory $category */
        $category = $this->record;

        return $category->fileTypes()->getQuery();
    }
}
