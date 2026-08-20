<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#f5f0e8">
        <meta name="color-scheme" content="light dark">

        @if (config('broadcasting.connections.pusher.key'))
            {{-- The Pusher app key and cluster are public client identifiers. The secret is never rendered. --}}
            <meta name="pusher-key" content="{{ config('broadcasting.connections.pusher.key') }}">
            <meta name="pusher-cluster" content="{{ config('broadcasting.connections.pusher.options.cluster', 'mt1') }}">
        @endif

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script nonce="{{ Vite::cspNonce() }}">
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }

                // Flag phones / low-end hardware before CSS + JS parse so the
                // first paint already skips backdrop-filter and looping animations.
                try {
                    var nav = navigator;
                    var coarse = ('ontouchstart' in window)
                        || (nav.maxTouchPoints > 0)
                        || window.matchMedia('(pointer: coarse)').matches;
                    var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                    var mem = nav.deviceMemory;
                    var cores = nav.hardwareConcurrency;
                    var conn = nav.connection && nav.connection.effectiveType;
                    var lowEnd = reduced || coarse
                        || (typeof mem === 'number' && mem <= 4)
                        || (typeof cores === 'number' && cores <= 4)
                        || conn === 'slow-2g'
                        || conn === '2g';
                    if (lowEnd) {
                        document.documentElement.setAttribute('data-low-end', '');
                    }
                } catch (e) {}
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style nonce="{{ Vite::cspNonce() }}">
            html {
                background-color: #f5f0e8;
            }

            html.dark {
                background-color: #000000;
            }
        </style>

        <title inertia>{{ config('app.name', 'Laravel') }}</title>
        <meta name="description" content="{{ config('seo.description', '') }}">

        {{--
            When a school logo is uploaded (admin → AiSettings → School Branding),
            serve it as the ONLY icon so browsers never fall back to the bundled
            Laravel logo. The URL is cache-busted (FaviconUrl::version()) so a
            freshly uploaded/changed logo appears immediately instead of being
            held back by the browser's aggressive favicon cache. With no logo
            set, fall back to the bundled static icons directly.
        --}}
        @if (\App\Support\FaviconUrl::hasLogo())
            <link rel="icon" href="{{ \App\Support\FaviconUrl::url() }}">
            <link rel="apple-touch-icon" href="{{ \App\Support\FaviconUrl::url(180) }}">
        @else
            <link rel="icon" href="/favicon.ico" sizes="any">
            <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        @endif

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link rel="preload" href="https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|inter:400,700,900" rel="stylesheet" />

        @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
        @if (view()->exists('impersonate::components.banner'))
            @include('impersonate::components.banner')
        @endif
    </body>
</html>
