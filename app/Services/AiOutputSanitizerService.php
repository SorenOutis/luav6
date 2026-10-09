<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Deterministic post-generation output sanitizer for Echo AI.
 * Modeled after EduFlow's AdvisorySanitizer:
 * scrubs sensitive credentials, prevents system prompt exfiltration,
 * and ensures output integrity before text reaches the user.
 */
class AiOutputSanitizerService
{
    /**
     * Sanitize output text from LLM before returning or streaming to the client.
     */
    public function sanitize(string $text): string
    {
        if (trim($text) === '') {
            return $text;
        }

        $sanitized = $text;

        // 1. Redact Environment Secret Patterns
        $secretPatterns = [
            '/(?:APP_KEY|APP_SECRET)\s*=\s*[^\s\r\n]+/i' => '[REDACTED_APP_KEY]',
            '/(?:AI_GATEWAY_KEY|GEMINI_API_KEY|GROQ_API_KEY|CLOUDFLARE_API_TOKEN|OPENAI_API_KEY)\s*[:=]\s*["\']?[a-zA-Z0-9_\-]{16,}["\']?/i' => '[REDACTED_API_KEY]',
            '/(?:DB_PASSWORD|REDIS_PASSWORD|MAIL_PASSWORD)\s*=\s*[^\s\r\n]+/i' => '[REDACTED_CREDENTIAL]',
            '/bearer\s+[a-zA-Z0-9_\-\.]{25,}/i' => 'Bearer [REDACTED_TOKEN]',
        ];

        foreach ($secretPatterns as $pattern => $replacement) {
            $sanitized = (string) preg_replace($pattern, $replacement, $sanitized);
        }

        // 2. Prevent Verbatim System Prompt Leakage
        $systemPromptSignatures = [
            '/WRITE-ACTION RULES \(strict\):/i',
            '/AUTHENTICATED USER & ACTIVE WORKSPACE CONTEXT/i',
            '/Is Super Admin: (?:YES|NO)/i',
        ];

        foreach ($systemPromptSignatures as $signature) {
            if (preg_match($signature, $sanitized)) {
                $sanitized = (string) preg_replace(
                    $signature,
                    '[Platform security policy: system configuration details redacted]',
                    $sanitized
                );
            }
        }

        return $sanitized;
    }
}
