<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CspReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $raw = $request->getContent();
        if (strlen($raw) > 65536) {
            return response('', 413);
        }

        $payload = json_decode($raw, true);
        $report = is_array($payload)
            ? ($payload['csp-report'] ?? $payload['body'] ?? $payload)
            : [];

        if (is_array($report)) {
            Log::notice('Content Security Policy violation', [
                'document_uri' => $this->safeUri($report['document-uri'] ?? $report['documentURL'] ?? null),
                'violated_directive' => $this->bounded($report['violated-directive'] ?? $report['effectiveDirective'] ?? null),
                'blocked_uri' => $this->safeUri($report['blocked-uri'] ?? $report['blockedURL'] ?? null),
                'source_file' => $this->safeUri($report['source-file'] ?? $report['sourceFile'] ?? null),
                'disposition' => $this->bounded($report['disposition'] ?? null),
                'status_code' => (int) ($report['status-code'] ?? $report['statusCode'] ?? 0),
                'user_id' => $request->user()?->id,
            ]);
        }

        return response('', 204);
    }

    private function safeUri(mixed $value): ?string
    {
        $value = $this->bounded($value);
        if ($value === null) {
            return null;
        }
        if (str_starts_with($value, 'data:')) {
            return 'data:';
        }
        if (! str_contains($value, '://')) {
            return $value;
        }

        $parts = parse_url($value);
        if (! is_array($parts)) {
            return null;
        }

        return ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '')
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .($parts['path'] ?? '/');
    }

    private function bounded(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        return mb_substr((string) $value, 0, 2048);
    }
}
