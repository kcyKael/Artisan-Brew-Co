@props(['title' => 'Sign In'])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — Artisan Brew Co.</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#111110] text-[#F5F0E8] antialiased min-h-screen flex items-center justify-center p-4 sm:p-6">
    {{ $slot }}
</body>
</html>
