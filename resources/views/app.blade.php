<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="app-name" content="{{ config('app.name') }}">
        <meta name="theme-color" content="#f5f0e8">
        <meta name="color-scheme" content="light dark">
        <meta name="google-site-verification" content="i5Bwmark4CmhXaDPV_4lxlMK2GBhXlDSkyS27p3s568" />

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
                    var updateTouchMobile = function() {
                        var touchMobile = coarse && window.innerWidth < 1024;
                        document.documentElement.classList.toggle('touch-mobile', touchMobile);
                    };
                    updateTouchMobile();
                    window.addEventListener('resize', updateTouchMobile, { passive: true });
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

        {{--
            Link-preview crawlers (Facebook, X, Slack, WhatsApp, LinkedIn,
            iMessage) never run JavaScript, so the og:/twitter: tags SeoHead
            writes client-side are invisible to them. Render the same metadata
            (and the real <title>) from the Inertia page payload server-side so
            the first HTML response already carries the social card.
        --}}
        @php
            $socialMeta = \App\Support\Seo::forPage($page ?? [], request());
        @endphp

        @if ($socialMeta !== null)
            <title inertia>{{ $socialMeta['title'] }} - {{ config('app.name') }}</title>
            <meta name="description" content="{{ $socialMeta['description'] }}">

            <link rel="canonical" href="{{ $socialMeta['canonical'] }}">
            <meta name="robots" content="{{ $socialMeta['robots'] }}">

            <meta property="og:type" content="{{ $socialMeta['type'] }}">
            <meta property="og:site_name" content="{{ $socialMeta['siteName'] }}">
            <meta property="og:title" content="{{ $socialMeta['title'] }}">
            <meta property="og:description" content="{{ $socialMeta['description'] }}">
            <meta property="og:url" content="{{ $socialMeta['canonical'] }}">
            @if ($socialMeta['image'] !== '')
                <meta property="og:image" content="{{ $socialMeta['image'] }}">
                <meta property="og:image:width" content="{{ $socialMeta['imageWidth'] }}">
                <meta property="og:image:height" content="{{ $socialMeta['imageHeight'] }}">
                <meta property="og:image:alt" content="{{ $socialMeta['imageAlt'] }}">
            @endif
            <meta property="og:locale" content="{{ $socialMeta['locale'] }}">

            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:title" content="{{ $socialMeta['title'] }}">
            <meta name="twitter:description" content="{{ $socialMeta['description'] }}">
            @if ($socialMeta['image'] !== '')
                <meta name="twitter:image" content="{{ $socialMeta['image'] }}">
            @endif
        @else
            {{-- Private/authenticated page: no social card, fallback title. --}}
            <title inertia>{{ config('app.name', 'Laravel') }}</title>
            <meta name="description" content="{{ config('seo.description', '') }}">
        @endif

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
        {{--
            Inter only — one family for the whole product (the instrument-sans
            payload was downloaded but no rule ever referenced it). Every weight
            the UI actually uses must be requested, otherwise the browser fakes
            or rounds it: font-medium (500) + font-semibold (600) collapse onto
            400/700 when they are missing, which makes every heading and button
            look slightly off-weight. font-black (900) is used by badges.
        --}}
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,900" rel="stylesheet" />

        @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
        @if (session()->has('impersonated_by'))
            <div id="impersonate-banner">
                <span>
                    Impersonating
                    <strong>{{ auth()->user()?->name }}</strong>
                </span>
                <a href="{{ url('/impersonation/leave') }}">Leave</a>
            </div>
            <style>
                #impersonate-banner {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    z-index: 50;
                    display: flex;
                    gap: 1rem;
                    align-items: center;
                    justify-content: center;
                    height: 50px;
                    background: #1f2937;
                    color: #f3f4f6;
                }
                #impersonate-banner a {
                    padding: 0.25rem 1rem;
                    border-radius: 0.375rem;
                    background: #f3f4f6;
                    color: #1f2937;
                }
            </style>
        @endif
    </body>
</html>
