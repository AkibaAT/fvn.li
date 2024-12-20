<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Games' }}</title>
    <meta name="description" content="{{ $description ?? 'Browse and discover games' }}">

    {{-- Open Graph / Social Media --}}
    <meta property="og:title" content="{{ $title ?? 'Games' }}" />
    <meta property="og:description" content="{{ $description ?? 'Browse and discover games' }}" />
    <meta property="og:type" content="website" />
    <meta property="og:url" content="{{ url()->current() }}" />

    @vite(['resources/css/app.css'])
</head>
<body class="bg-gray-100">
{{ $slot }}

@livewireScripts
</body>
</html>
