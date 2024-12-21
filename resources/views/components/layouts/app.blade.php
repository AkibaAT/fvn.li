<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

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
    @livewireStyles
</head>
<body class="min-h-screen bg-gray-100 dark:bg-gray-900">
{{ $slot }}

@livewireScripts
</body>
</html>
