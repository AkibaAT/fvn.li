<!DOCTYPE html>
@php
    $appearance = request()->cookie('appearance');
    $isDark = $appearance === 'dark';
    $iconVersion = max(
        filemtime(public_path('favicon.ico')),
        filemtime(public_path('icon-192.png')),
        filemtime(public_path('apple-touch-icon.png')),
    );
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $isDark ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="canonical" href="{{ canonical() }}"/>

    <link rel="icon" href="{{ asset('favicon.ico') }}?v={{ $iconVersion }}" sizes="any">
    <link rel="icon" href="{{ asset('icon-192.png') }}?v={{ $iconVersion }}" type="image/png" sizes="192x192">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v={{ $iconVersion }}">

    <link rel="manifest" href="{{ asset('manifest.json') }}?v={{ $iconVersion }}">

    <link rel="alternate" type="application/rss+xml" title="FVN.li - New Visual Novels" href="{{ route('feed.new') }}">
    <link rel="alternate" type="application/rss+xml" title="FVN.li - Updated Visual Novels" href="{{ route('feed.updates') }}">

    @if (!app()->environment('production'))
        <meta name="robots" content="noindex, nofollow">
    @endif

    <meta name="theme-color" content="#3B82F6">
    <meta name="msapplication-TileColor" content="#3B82F6">
    <meta name="application-name" content="FVN.li">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="FVN.li">

    @inertiaHead

    @php
        $metaTags = $page['props']['metaTags'] ?? [];
        $seoTitle = $metaTags['browserTitle'] ?? $metaTags['title'] ?? null;
        $socialTitle = $metaTags['socialTitle'] ?? $seoTitle;
    @endphp

    @if ($seoTitle)
        <title>{{ $seoTitle }} - FVN.li</title>
    @endif

    @if (!empty($metaTags['description']))
        <meta name="description" content="{{ $metaTags['description'] }}">
    @endif

    @if (!empty($metaTags['author']))
        <meta name="author" content="{{ $metaTags['author'] }}">
    @endif

    @if (!empty($metaTags['noindex']) && $metaTags['noindex'])
        <meta name="robots" content="noindex">
    @endif

    @if ($socialTitle)
        <meta property="og:title" content="{{ $socialTitle }}">
    @endif
    @if (!empty($metaTags['description']))
        <meta property="og:description" content="{{ $metaTags['description'] }}">
    @endif
    @if (!empty($metaTags['image']))
        <meta property="og:image" content="{{ $metaTags['image'] }}">
    @endif
    @if (!empty($metaTags['url']))
        <meta property="og:url" content="{{ $metaTags['url'] }}">
    @endif
    <meta property="og:type" content="{{ $metaTags['type'] ?? 'website' }}">
    <meta property="og:site_name" content="{{ $metaTags['siteName'] ?? 'FVN.li' }}">
    <meta property="og:locale" content="{{ $metaTags['locale'] ?? 'en_US' }}">

    @if (!empty($metaTags['publishedTime']))
        <meta property="article:published_time" content="{{ $metaTags['publishedTime'] }}">
    @endif
    @if (!empty($metaTags['modifiedTime']))
        <meta property="article:modified_time" content="{{ $metaTags['modifiedTime'] }}">
    @endif
    @if (!empty($metaTags['author']))
        <meta property="article:author" content="{{ $metaTags['author'] }}">
    @endif
    @if (!empty($metaTags['section']))
        <meta property="article:section" content="{{ $metaTags['section'] }}">
    @endif
    @if (!empty($metaTags['tags']))
        @foreach ($metaTags['tags'] as $tag)
            <meta property="article:tag" content="{{ $tag }}">
        @endforeach
    @endif

    <meta name="twitter:card" content="{{ $metaTags['twitterCard'] ?? 'summary_large_image' }}">
    @if ($socialTitle)
        <meta name="twitter:title" content="{{ $socialTitle }}">
    @endif
    @if (!empty($metaTags['description']))
        <meta name="twitter:description" content="{{ $metaTags['description'] }}">
    @endif
    @if (!empty($metaTags['image']))
        <meta name="twitter:image" content="{{ $metaTags['image'] }}">
    @endif
    @if (!empty($metaTags['url']))
        <meta name="twitter:url" content="{{ $metaTags['url'] }}">
    @endif

    @if (!empty($metaTags['structuredData']))
        @php
            $structuredDataJson = json_encode(
                array_merge(['@context' => 'https://schema.org'], $metaTags['structuredData']),
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_HEX_TAG
                    | JSON_HEX_AMP
                    | JSON_HEX_APOS
                    | JSON_HEX_QUOT
            );
        @endphp
        <script type="application/ld+json">{!! $structuredDataJson !!}</script>
    @endif

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

    @vite('resources/js/app.ts')
    @routes
</head>
<body class="bg-gray-100 dark:bg-gray-900 min-h-screen antialiased">
@inertia
</body>
</html>
