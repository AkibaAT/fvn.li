<?php

declare(strict_types=1);

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Models\DiscordServer;
use App\Models\UserGameProgress;
use App\Models\VnList;
use App\Policies\DiscordServerPolicy;
use App\Policies\UserGameProgressPolicy;
use App\Policies\VnListPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        DiscordServer::class => DiscordServerPolicy::class,
        VnList::class => VnListPolicy::class,
        UserGameProgress::class => UserGameProgressPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
