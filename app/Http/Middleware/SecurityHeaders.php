<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        /** @var Response $response */
        $response = $next($request);

        $isGraphify = $request->routeIs('admin.graphify.*');
        $canEmbedInFrame = $isGraphify || $request->routeIs('library.file');

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', $canEmbedInFrame ? 'SAMEORIGIN' : 'DENY');
        $response->headers->set('Referrer-Policy', (string) config('security.referrer_policy'));
        $response->headers->set('Permissions-Policy', (string) config('security.permissions_policy'));
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        // Prevent browsers and intermediate proxies from serving stale HTML/Inertia documents.
        // Hashed Vite assets (/build/assets/*) are immutable, but the HTML shell referencing them
        // must always revalidate with the server so deployments and fixes take effect immediately.
        if (! $request->is('build/*', 'images/*', 'fonts/*', 'favicon.*', 'brand/*')) {
            $contentType = (string) $response->headers->get('Content-Type');
            $isDownload = str_contains((string) $response->headers->get('Content-Disposition'), 'attachment');
            if (! $isDownload && (str_contains($contentType, 'text/html') || $request->header('X-Inertia') || $contentType === '')) {
                $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
                $response->headers->set('Pragma', 'no-cache');
                $response->headers->set('Expires', '0');
            }
        }

        if ($this->shouldSendHsts($request)) {
            $response->headers->set('Strict-Transport-Security', $this->hstsValue());
        }

        $mode = (string) config('security.csp_mode', 'report-only');
        if (in_array($mode, ['report-only', 'enforce'], true)) {
            $header = $mode === 'enforce'
                ? 'Content-Security-Policy'
                : 'Content-Security-Policy-Report-Only';
            $response->headers->set($header, $this->contentSecurityPolicy($request));
        }

        return $response;
    }

    private function contentSecurityPolicy(Request $request): string
    {
        $nonce = Vite::cspNonce();
        $isGraphify = $request->routeIs('admin.graphify.*');
        $canEmbedInFrame = $isGraphify || $request->routeIs('library.file');

        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            'object-src '.($canEmbedInFrame ? "'self'" : "'none'"),
            'frame-ancestors '.($canEmbedInFrame ? "'self'" : "'none'"),
            "form-action 'self'",
            "script-src 'self' ".($isGraphify ? "'unsafe-inline' https://unpkg.com" : "'nonce-{$nonce}' https://js.pusher.com"),
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            "font-src 'self' data: https://fonts.bunny.net",
            "img-src 'self' data: blob: https:",
            "media-src 'self' blob: https:",
            "connect-src 'self' https: wss:",
            "frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com https://player.vimeo.com",
            "worker-src 'self' blob:",
            "manifest-src 'self'",
        ];

        $reportUri = trim((string) config('security.csp_report_uri'));
        if ($reportUri !== '') {
            $directives[] = 'report-uri '.$reportUri;
        }

        return implode('; ', $directives);
    }

    private function shouldSendHsts(Request $request): bool
    {
        return (bool) config('security.hsts.enabled', true)
            && ($request->isSecure() || app()->isProduction());
    }

    private function hstsValue(): string
    {
        $value = 'max-age='.max(0, (int) config('security.hsts.max_age', 31536000));
        if (config('security.hsts.include_subdomains', true)) {
            $value .= '; includeSubDomains';
        }
        if (config('security.hsts.preload', false)) {
            $value .= '; preload';
        }

        return $value;
    }
}
