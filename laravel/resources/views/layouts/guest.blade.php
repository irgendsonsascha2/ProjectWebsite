<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        {{-- Alte Projektoptik (Account / Login wie pages/account.php) — nach Tailwind laden --}}
        <link href="{{ asset('css/theme-vars.css') }}" rel="stylesheet">
        <link href="{{ asset('css/account.css') }}" rel="stylesheet">
        <style>
            /* Tailwind neutra­lisieren wo nötig für den Legacy-„Karten“-Look */
            .guest-legacy-shell { box-sizing: border-box; min-height: 100vh; background: #f9f9f9; padding: 1.25rem; }
            .guest-legacy-shell * { box-sizing: border-box; }
        </style>
    </head>
    <body class="guest-legacy-shell antialiased">
        <div class="container">
            <div class="mb-6 text-center">
                <a href="{{ url('/') }}" class="inline-block text-xl font-semibold text-[var(--text-main,#1e293b)]">
                    {{ config('app.name') }}
                </a>
            </div>

            {{ $slot }}
        </div>
    </body>
</html>
