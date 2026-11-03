<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark']) @isset($realmThemeStylesheet) data-realm-theme @endisset @isset($forcedAppearance) data-forced-appearance="{{ $forcedAppearance }}" @endisset>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="application-name" content="{{ config('app.name') }}">

        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color before the stylesheet loads; values mirror lattice's --lt-bg defaults --}}
        <style>
            html {
                background-color: oklch(0.976 0.003 210);
            }

            html.dark {
                background-color: oklch(0.155 0.008 225);
            }
        </style>

        @isset($realmThemeStylesheet)
            <style>{!! $realmThemeStylesheet !!}</style>
        @endisset

        @if ($realmFavicon ?? null)
            <link rel="icon" href="{{ $realmFavicon }}">
        @else
            <link rel="icon" href="/favicon.ico" sizes="any">
            <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        @endif
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        <x-inertia::head>
            <title>{{ config('app.name', 'Lock') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
