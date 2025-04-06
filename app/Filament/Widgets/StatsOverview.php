<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\User;
use App\Models\VnList;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Games', Game::count())
                ->description('Total games in the database')
                ->descriptionIcon('heroicon-m-rectangle-stack')
                ->color('primary'),

            Stat::make('Visible Games', Game::where('is_visible', true)->count())
                ->description('Games visible to users')
                ->descriptionIcon('heroicon-m-eye')
                ->color('success'),

            Stat::make('Game Versions', GameVersion::count())
                ->description('Total game versions tracked')
                ->descriptionIcon('heroicon-m-tag')
                ->color('warning'),

            Stat::make('Registered Users', User::count())
                ->description('Total registered users')
                ->descriptionIcon('heroicon-m-user')
                ->color('info'),

            Stat::make('User Lists', VnList::count())
                ->description('Total user-created lists')
                ->descriptionIcon('heroicon-m-list-bullet')
                ->color('danger'),

            Stat::make('Admin Users', User::where('is_admin', true)->count())
                ->description('Users with admin access')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('gray'),
        ];
    }
}
