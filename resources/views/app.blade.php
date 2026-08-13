<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#f5f0e8">
        <meta name="color-scheme" content="light dark">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
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

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: #f5f0e8;
            }

            html.dark {
                background-color: #000000;
            }
        </style>

        <title inertia>{{ config('app.name', 'Laravel') }}</title>
        <meta name="description" content="{{ config('seo.description', '') }}">

        {{-- The dynamic route serves the uploaded school logo; it falls back to the bundled static icons below when none is set. --}}
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="{{ route('favicon') }}" type="image/png">
        <link rel="apple-touch-icon" href="{{ route('favicon', ['size' => 180]) }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link rel="preload" href="https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|inter:400,700,900" rel="stylesheet" />

        @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
