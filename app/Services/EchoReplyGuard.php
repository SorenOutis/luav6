<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PendingAiAction;
use App\Models\User;

/**
 * Post-model reply guard for Echo admin turns.
 *
 * The prompt already forbids inventing approval cards and download filenames.
 * This class enforces those rules in code before the message is persisted or
 * streamed to the client.
 */
final class EchoReplyGuard
{
    /**
     * @param  array{pending_id?: string|null, expires_at?: string|null, title?: string|null, summary?: string|null}|null  $staged
     */
    public function enforce(string $text, bool $stagedThisTurn, ?array $staged = null): string
    {
        $text = trim($text);

        if (! $stagedThisTurn) {
            $text = $this->stripInventedApprovalClaims($text);
            $text = $this->stripInventedExportClaims($text);
        } elseif (is_array($staged) && ($staged['pending_id'] ?? null)) {
            $text = $this->ensureStagedFactsPresent($text, $staged);
        }

        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }

    public function stagedActionForSession(?User $user, ?int $chatSessionId): ?PendingAiAction
    {
        if (! $user || ! $chatSessionId) {
            return null;
        }

        return PendingAiAction::query()
            ->where('user_id', $user->id)
            ->where('chat_session_id', $chatSessionId)
            ->where('status', PendingAiAction::STATUS_PENDING)
            ->where('created_at', '>=', now()->subMinutes(2))
            ->latest('id')
            ->first();
    }

    /**
     * @return array{pending_id: string, expires_at: string|null, title: string, summary: string}|null
     */
    public function stagedSummary(?PendingAiAction $action): ?array
    {
        if (! $action) {
            return null;
        }

        return [
            'pending_id' => (string) $action->public_id,
            'expires_at' => $action->expires_at?->toIso8601String(),
            'title' => (string) $action->title,
            'summary' => (string) ($action->summary ?? ''),
        ];
    }

    private function stripInventedApprovalClaims(string $text): string
    {
        $patterns = [
            '/\bready for (your )?approval\b/iu',
            '/\bapproval card (is|was) (now )?visible\b/iu',
            '/\bPENDING HUMAN APPROVAL\b/u',
            '/\bstaged (an? )?(immutable )?approval\b/iu',
            '/\bclick Approve\b/iu',
            '/\breview the exact diff\b/iu',
        ];

        foreach ($patterns as $pattern) {
            $text = preg_replace($pattern, '', $text) ?? $text;
        }

        return $text;
    }

    private function stripInventedExportClaims(string $text): string
    {
        $text = preg_replace(
            '/\b[\w.-]+\.(csv|xlsx|docx|pdf)\b/iu',
            '[export pending approval]',
            $text
        ) ?? $text;

        $text = preg_replace(
            '#https?://[^\s]+/ai-reports/[^\s]+#iu',
            '[download available after approval]',
            $text
        ) ?? $text;

        return $text;
    }

    /**
     * @param  array{pending_id: string, expires_at: string|null, title: string, summary: string}  $staged
     */
    private function ensureStagedFactsPresent(string $text, array $staged): string
    {
        if (! str_contains($text, $staged['pending_id'])) {
            $expiry = $staged['expires_at'] ? ' Expires '.$staged['expires_at'].'.' : '';
            $text .= "\n\nApproval card: {$staged['title']} (id {$staged['pending_id']}).{$expiry}";
        }

        return $text;
    }
}
