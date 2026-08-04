<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDiscordServerBotEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('services.discord.server_bot_enabled'), 404);

        return $next($request);
    }
}
