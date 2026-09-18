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

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', (string) config('security.referrer_policy'));
        $response->headers->set('Permissions-Policy', (string) config('security.permissions_policy'));
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($this->shouldSendHsts($request)) {
            $response->headers->set('Strict-Transport-Security', $this->hstsValue());
        }

        $mode = (string) config('security.csp_mode', 'report-only');
        if (in_array($mode, ['report-only', 'enforce'], true)) {
            $header = $mode === 'enforce'
                ? 'Content-Security-Policy'
                : 'Content-Security-Policy-Report-Only';
            $response->headers->set($header, $this->contentSecurityPolicy());
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        $nonce = Vite::cspNonce();
        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "script-src 'self' 'nonce-{$nonce}' https://js.pusher.com",
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
