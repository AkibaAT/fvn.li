<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\GameFilterService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'avatar' => $request->user()->avatar,
                    'is_admin' => $request->user()->is_admin ?? false,
                ] : null,
            ],
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'ziggy' => fn (): array => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'userPreferences' => fn () => $request->user()?->preferences?->only('preferred_languages'),
            // Only provide heavy game filter options on routes that need them
            'gameFilters' => fn () => $request->routeIs('games.*') ? GameFilterService::getOptions() : null,
            // SEO meta information
            'seo' => [
                'noindex' => false, // Default to false, controllers can override
                'canonical' => canonical(),
            ],

            // Global lightweight counts for UI indicators
            'indicators' => function () use ($request) {
                $user = $request->user();
                if (! $user) {
                    return [
                        'pending_invites' => 0,
                        'unread_notifications' => 0,
                    ];
                }
                $unreadNotifications = $user->unreadNotifications()->count();

                return [
                    'pending_invites' => 0,
                    'unread_notifications' => $unreadNotifications,
                ];
            },
        ];
    }
}
