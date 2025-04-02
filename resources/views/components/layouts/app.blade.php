@props(['centered' => false])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @if (isset($noindex) && $noindex)
        <meta name="robots" content="noindex">
    @endif

    <link rel="canonical" href="{{ canonical() }}"/>

    @php
        $metaTags = $metaTags ?? null;
    @endphp

    {{-- Dynamic Meta Tags --}}
    <title>{{ $metaTags['title'] ?? config('app.name') }}</title>
    <meta name="description" content="{{ $metaTags['description'] ?? config('app.description', '') }}">

    {{-- Open Graph / Facebook --}}
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="{{ $metaTags['title'] ?? config('app.name') }}">
    <meta property="og:description" content="{{ $metaTags['description'] ?? config('app.description', '') }}">
    <meta property="og:image" content="{{ $metaTags['image'] ?? asset('favicon.ico') }}">

    {{-- Twitter --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="{{ url()->current() }}">
    <meta name="twitter:title" content="{{ $metaTags['title'] ?? config('app.name') }}">
    <meta name="twitter:description" content="{{ $metaTags['description'] ?? config('app.description', '') }}">
    <meta name="twitter:image" content="{{ $metaTags['image'] ?? asset('favicon.ico') }}">

    @vite(['resources/css/app.css'])

    {{-- Telegram Login Widget --}}
    <script async src="https://telegram.org/js/telegram-widget.js?22"></script>
</head>
<body class="bg-gray-100 dark:bg-gray-900 min-h-screen flex flex-col">
    <x-layouts.header />

    @if ($centered)
        <div class="flex-grow flex items-center justify-center">
            <div class="max-w-7xl mx-auto px-4 sm:px-8 xs:px-2">
                {{ $slot }}
            </div>
        </div>
    @else
        <main class="py-3 mt-3">
            <div class="max-w-7xl mx-auto px-4 sm:px-8 xs:px-2">
                {{ $slot }}
            </div>
        </main>
    @endif

    @stack('scripts')
    <!-- Footer -->
    <footer class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-8 xs:px-2">
            <!-- Horizontal Line -->
            <div class="border-t border-gray-200 dark:border-gray-700"></div>

            <!-- Footer Content -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mt-8">
                <!-- About Section -->
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">About</h3>
                    <div class="space-y-3 text-sm text-gray-500 dark:text-gray-400">
                        <p>
                            The visibility of entries on this list is being controlled by the
                            <a href="https://itch.io/c/5469099/fvnli-watchlist"
                               class="text-blue-600 dark:text-blue-400 hover:underline"
                               target="_blank">
                                itch.io collection</a>.
                        </p>
                        <p>
                            Updates and ratings are pulled from the itch.io feed every 15 minutes.
                        </p>
                        <p>
                            All source code for this page can be found on
                            <a href="https://github.com/AkibaAT/fvn.li"
                               class="text-blue-600 dark:text-blue-400 hover:underline inline-flex items-center space-x-1"
                               target="_blank">
                                <i class="icon-github"></i>
                                <span>GitHub</span>
                            </a>.
                        </p>
                    </div>
                </div>
                <!-- Contact Section -->
                <div class="md:flex md:justify-center">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Contact</h3>
                        <div class="flex flex-col space-y-3">
                            <a href="https://bsky.app/profile/akiba.at"
                               class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 flex items-center space-x-2"
                               target="_blank"
                               title="Contact on Bluesky">
                                <i class="icon-bluesky w-5 text-center"></i>
                                <span>@akiba.at</span>
                            </a>
                            <a href="https://discord.com/users/akiba.at"
                               class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 flex items-center space-x-2"
                               target="_blank"
                               title="Contact on Discord">
                                <i class="icon-discord w-5 text-center"></i>
                                <span>@akiba.at</span>
                            </a>
                            <a href="https://t.me/AkibaAT"
                               class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 flex items-center space-x-2"
                               target="_blank"
                               title="Contact on Telegram">
                                <i class="icon-telegram w-5 text-center"></i>
                                <span>@AkibaAT</span>
                            </a>
                        </div>
                    </div>
                </div>
                <!-- Quick Access Section -->
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Quick Access</h3>
                    <div class="space-y-3 text-sm text-gray-500 dark:text-gray-400">
                        <p>
                            Add the following link to your bookmarks to quickly access ratings from any itch.io project
                            page, including those not listed on FVN.li:
                        </p>
                        <a href="javascript:(function(){var%20e=window.location.hostname,t=window.location.pathname.split('/')[1];if(e.endsWith('.itch.io')%26%26e!=='itch.io'%26%26e!=='www.itch.io'%26%26t){window.open('https://fvn.li/by-url/'+window.location.origin+'/'+t,'_blank')}})();"
                           class="inline-flex items-center px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-200 rounded text-sm font-medium transition-colors"
                           title="Drag to bookmarks bar">
                            <span>📘 FVN Ratings Link</span>
                        </a>
                        <p class="text-xs text-gray-400 dark:text-gray-500">
                            Works on pages with URLs like "creator.itch.io/project-name"
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </footer>
    @stack('before-body-end')
</body>
</html>
