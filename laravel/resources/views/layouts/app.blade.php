<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <link href="{{ asset('css/theme-vars.css') }}" rel="stylesheet">
        <link href="{{ asset('css/style.css') }}" rel="stylesheet">
        <style>
            /* Laravel-Breeze nutzt eigene Nav-Struktur — leicht an Legacy anpassen */
            body { background-color: var(--bg-color) !important; color: var(--text-main); }
            header.bg-white.shadow { background-color: var(--card-bg) !important; box-shadow: var(--shadow) !important; }
        </style>
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen flex flex-col bg-[var(--bg-color)]">
            @include('layouts.navigation')

            @isset($header)
                <header class="bg-white shadow border-b border-slate-200/80">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main class="flex-1 w-[90%] max-w-7xl mx-auto my-8 px-4 py-4 md:px-6">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
