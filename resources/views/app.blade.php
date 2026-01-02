<!DOCTYPE html>
@php
    $appearance = request()->cookie('appearance');
    $isDark = $appearance === 'dark';
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $isDark ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Canonical URL -->
    <link rel="canonical" href="{{ canonical() }}"/>

    <!-- Favicon -->
    <link rel="icon" href="{{ asset('favicon.ico') }}">

    <!-- Robots meta tag for non-production environments -->
    @if (!app()->environment('production'))
        <meta name="robots" content="noindex, nofollow">
    @endif

    <!-- Essential Meta Tags - Dynamic content provided by Inertia Head -->
    <meta name="theme-color" content="#f97316">
    <meta name="msapplication-TileColor" content="#f97316">
    <meta name="application-name" content="FVN.li">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="FVN.li">

    <!-- Dynamic SEO Tags -->
    @inertiaHead

    <!-- Structured Data Placeholder -->
    @yield('structured-data')

    @routes

    <!-- Prevent theme flash: set dark class ASAP based on localStorage/cookie/system -->
    <script>
        (function () {
            try {
                var doc = document.documentElement;
                var stored = localStorage.getItem('appearance') || 'system';
                var isDark = (stored === 'dark') || (stored === 'system' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);

                if (isDark) {
                    doc.classList.add('dark');
                } else {
                    doc.classList.remove('dark');
                }
            } catch (e) {
                // no-op
            }
        })();
    </script>

    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
</head>
<body class="min-h-screen antialiased app-body bg-texture">
@inertia
</body>
</html>
