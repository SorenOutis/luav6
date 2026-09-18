{{--
    Self-contained HTTP error page layout.

    Error views must render even when the rest of the app is unavailable
    (maintenance mode, 500s, missing manifests), so this deliberately does NOT
    use the Vite bundle — CSS is inlined and the only external assets are the
    pre-generated /images/errors/*.webp illustrations (with .png fallbacks).
    It mirrors the app's warm cream theme (light) and warm-brown theme
    (dark) from resources/css.

    Variables provided by Laravel's exception renderer:
      $status      – HTTP status code (int)
      $exception   – the thrown Throwable (may be null)
      $errors??    – not guaranteed here; do not rely on shared view data.
--}}
@php
    // Laravel renders these views with `$exception` (and sometimes `$status`).
    // Resolve the code defensively so the layout works for both.
    $status = isset($status)
        ? (int) $status
        : (isset($exception) && $exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
            ? $exception->getStatusCode()
            : 500);

    // Curated, user-facing copy per status. The exception's own message is
    // preferred for 4xx (abort() messages are written for end users) and only
    // shown for 5xx when APP_DEBUG is on, to avoid leaking internals.
    $copy = [
        400 => ['Bad request', 'That request could not be understood. Please refresh and try again.'],
        401 => ['You need to sign in', "You're signed out — or haven't signed in yet. Log in and try again."],
        403 => ["You don't have access", "You're signed in, but this page isn't available to your account. If you think that's a mistake, contact your teacher or administrator."],
        404 => ["We can't find that page", 'The page you were looking for may have been moved, removed, or never existed. Double-check the address or head back home.'],
        409 => ['That has already been done', 'This action was already completed, or the record changed in the meantime. Refresh to see the latest state and try again.'],
        410 => ['This is gone for good', 'The content you were looking for has been permanently removed and cannot be recovered.'],
        419 => ['Your session expired', 'For your security, this page timed out. Refresh and try again — you may need to sign back in.'],
        422 => ['Some details need fixing', 'Your submission could not be processed. Go back, review your input, and try again.'],
        423 => ['This page is locked', 'This area has been temporarily turned off by your school. Please check back later — your work is safe.'],
        429 => ['Too many requests', "You're going a little fast. Please wait a moment and try again."],
        500 => ['Something went wrong', 'An unexpected problem happened on our side. We have been notified — please try again in a moment.'],
        502 => ['The server hiccuped', 'A gateway error occurred while handling your request. Please try again in a moment.'],
        503 => ['Be right back', 'The system is temporarily down for maintenance or updates. Please try again shortly.'],
        504 => ['This is taking too long', 'The server timed out while waiting for a response. Please try again in a moment.'],
    ];

    $isServerError = $status >= 500;
    [$title, $fallbackMessage] = $copy[$status] ?? ['Something went wrong', 'An unexpected error occurred. Please try again in a moment.'];

    // Prefer a human-written abort() message on client errors; never trust
    // raw exception messages on server errors unless debug mode is enabled.
    $detail = null;
    if (isset($exception) && $exception instanceof Throwable) {
        $raw = trim($exception->getMessage());
        $genericPhrase = strtolower(\Symfony\Component\HttpFoundation\Response::$statusTexts[$status] ?? '') === strtolower($raw);
        if ($raw !== '' && ! $genericPhrase) {
            if ($isServerError) {
                $detail = config('app.debug') ? $raw : null;
            } else {
                $detail = $raw;
            }
        }
    }
    // For client errors the abort() message IS the primary message, so it
    // never needs a separate detail box. For server errors the primary
    // message stays a safe generic line and the raw exception message is
    // only surfaced (below) when debug mode is on.
    $message = $isServerError ? $fallbackMessage : ($detail ?? $fallbackMessage);
    if (! $isServerError) {
        $detail = null;
    }

    // Illustration: a dedicated image per status, then the generic "error"
    // art, falling back to 500 for server errors and 404 for anything else.
    // Files are pre-generated as .webp (primary) with .png fallbacks.
    $illustration = null;
    foreach ([$status, $isServerError ? 500 : 404, 'error'] as $candidate) {
        $webp = public_path("images/errors/{$candidate}.webp");
        $png = public_path("images/errors/{$candidate}.png");
        if (file_exists($webp) || file_exists($png)) {
            $illustration = [
                'webp' => file_exists($webp) ? "/images/errors/{$candidate}.webp" : null,
                'png' => file_exists($png) ? "/images/errors/{$candidate}.png" : null,
            ];
            break;
        }
    }

    $appName = config('app.name', 'LSIv6');
    $appearance = $_COOKIE['appearance'] ?? 'system';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <meta name="theme-color" content="#f5f0e8">
        <title>{{ $status }} — {{ $title }} · {{ $appName }}</title>

        <script nonce="{{ Vite::cspNonce() }}">
            (function () {
                try {
                    var appearance = @json($appearance);
                    if (appearance === 'dark' ||
                        (appearance === 'system' &&
                            window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                        document.documentElement.classList.add('dark');
                    }
                } catch (e) {}
            })();
        </script>

        <style>
            :root {
                --bg: #f5f0e8;
                --fg: #1a1a1e;
                --card: #faf7f2;
                --muted-fg: #7a756e;
                --border: #e0dbd3;
                --muted: #ebe5dc;
                --accent: #c66b4a;
                --sage: #6b8f71;
            }
            html.dark {
                --bg: #1a1410;
                --fg: #f5f0eb;
                --card: #221c18;
                --muted-fg: #a09890;
                --border: #3a322c;
                --muted: #2a2420;
                --accent: #e08a63;
                --sage: #8fb896;
            }
            * { box-sizing: border-box; margin: 0; padding: 0; }
            html, body { height: 100%; }
            body {
                font-family: Inter, ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
                background: var(--bg);
                color: var(--fg);
                -webkit-font-smoothing: antialiased;
                line-height: 1.5;
            }
            .page {
                min-height: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 2.5rem 1.25rem;
            }
            .card {
                width: 100%;
                max-width: 34rem;
                background: var(--card);
                border: 1px solid var(--border);
                border-radius: 1.25rem;
                padding: 2.5rem 2rem 2rem;
                text-align: center;
                box-shadow: 0 1px 2px rgba(26, 26, 30, 0.04), 0 12px 32px -12px rgba(26, 26, 30, 0.18);
            }
            .art { margin: 0 auto; width: 100%; max-width: 17rem; }
            .art img {
                width: 100%;
                height: auto;
                display: block;
                border-radius: 1rem;
            }
            .badge {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                margin-top: 1.5rem;
                min-width: 4.25rem;
                padding: 0.35rem 0.85rem;
                border-radius: 999px;
                background: var(--muted);
                color: var(--muted-fg);
                font-family: ui-monospace, 'JetBrains Mono', 'Fira Code', SFMono-Regular, Menlo, monospace;
                font-size: 0.85rem;
                font-weight: 700;
                letter-spacing: 0.08em;
            }
            h1 {
                margin-top: 0.9rem;
                font-size: clamp(1.4rem, 4vw, 1.75rem);
                font-weight: 800;
                letter-spacing: -0.01em;
            }
            p.message {
                margin: 0.75rem auto 0;
                max-width: 27rem;
                font-size: 0.95rem;
                color: var(--muted-fg);
            }
            .detail {
                margin: 1rem auto 0;
                max-width: 27rem;
                padding: 0.75rem 1rem;
                border-radius: 0.75rem;
                background: var(--muted);
                border: 1px dashed var(--border);
                font-size: 0.8rem;
                color: var(--muted-fg);
                text-align: left;
                word-break: break-word;
            }
            .actions {
                margin-top: 1.75rem;
                display: flex;
                flex-wrap: wrap;
                gap: 0.6rem;
                justify-content: center;
            }
            .btn {
                display: inline-flex;
                align-items: center;
                gap: 0.45rem;
                padding: 0.6rem 1.15rem;
                border-radius: 0.65rem;
                font-size: 0.875rem;
                font-weight: 600;
                text-decoration: none;
                border: 1px solid transparent;
                cursor: pointer;
                transition: transform 0.12s ease, background 0.15s ease, border-color 0.15s ease;
            }
            .btn:active { transform: scale(0.97); }
            .btn-primary { background: var(--fg); color: var(--bg); }
            .btn-primary:hover { opacity: 0.88; }
            .btn-secondary {
                background: transparent;
                color: var(--fg);
                border-color: var(--border);
            }
            .btn-secondary:hover { background: var(--muted); }
            .footer {
                margin-top: 2rem;
                padding-top: 1.25rem;
                border-top: 1px solid var(--border);
                font-size: 0.78rem;
                color: var(--muted-fg);
            }
            .footer a { color: inherit; font-weight: 600; text-decoration: none; }
            .footer a:hover { color: var(--accent); }
            @media (prefers-reduced-motion: reduce) {
                .btn { transition: none; }
            }
        </style>
    </head>
    <body>
        <main class="page">
            <section class="card" role="alert">
                @if ($illustration)
                    <div class="art">
                        <picture>
                            @if ($illustration['webp'])
                                <source srcset="{{ $illustration['webp'] }}" type="image/webp">
                            @endif
                            <img
                                src="{{ $illustration['png'] ?? $illustration['webp'] }}"
                                alt=""
                                width="720"
                                height="394"
                                loading="eager"
                                decoding="async"
                            >
                        </picture>
                    </div>
                @endif

                <span class="badge">ERROR {{ $status }}</span>
                <h1>{{ $title }}</h1>
                <p class="message">{{ $message }}</p>

                @if ($detail)
                    <p class="detail">{{ $detail }}</p>
                @endif

                <div class="actions">
                    <a class="btn btn-primary" href="{{ url('/') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/></svg>
                        Back to home
                    </a>

                    @if (in_array($status, [401, 419], true))
                        <a class="btn btn-secondary" href="{{ url('/login') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" x2="3" y1="12" y2="12"/></svg>
                            Sign in
                        </a>
                    @endif

                    @if (in_array($status, [409, 419, 500, 502, 503, 504], true))
                        <button type="button" class="btn btn-secondary" data-action="reload">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>
                            Try again
                        </button>
                    @else
                        <button type="button" class="btn btn-secondary" data-action="back">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
                            Go back
                        </button>
                    @endif
                </div>

                <div class="footer">
                    <a href="{{ url('/') }}">{{ $appName }}</a> · If this keeps happening, contact your teacher or administrator.
                </div>
            </section>
        </main>

        <script nonce="{{ Vite::cspNonce() }}">
            document.querySelectorAll('[data-action]').forEach(function (el) {
                el.addEventListener('click', function () {
                    var action = el.getAttribute('data-action');
                    if (action === 'reload') {
                        window.location.reload();
                    } else if (action === 'back' && window.history.length > 1) {
                        window.history.back();
                    } else {
                        window.location.href = @json(url('/'));
                    }
                });
            });
        </script>
    </body>
</html>
