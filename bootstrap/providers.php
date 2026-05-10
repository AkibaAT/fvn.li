<?php

declare(strict_types=1);
use App\Providers\Filament\AdminPanelProvider;
use SocialiteProviders\Manager\ServiceProvider;

return [
    AdminPanelProvider::class,
    ServiceProvider::class,
];
