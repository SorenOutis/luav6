<?php

declare(strict_types=1);

namespace App\Ai\Watchers\Checks;

use App\Ai\Watchers\BotFinding;
use App\Ai\Watchers\BotWatcherCheck;
use App\Models\AiBot;
use App\Models\Announcement;
use App\Models\SupportTicket;
use App\Models\Workspace;

/**
 * Communications Lead: the support inbox must not grow silent replies.
 *
 * Only aggregates and ticket ages are collected; ticket subjects and message
 * bodies are user content and never surface in findings.
 */
class CommunicationsWatcherCheck implements BotWatcherCheck
{
    public const STALE_HOURS = 24;

    public const QUIET_ANNOUNCEMENT_DAYS = 7;

    public function domain(): string
    {
        return 'communications';
    }

    public function run(AiBot $bot, Workspace $workspace): array
    {
        $findings = [];
        $now = now();

        $staleCount = SupportTicket::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('status', [SupportTicket::STATUS_OPEN, SupportTicket::STATUS_IN_PROGRESS])
            ->whereDoesntHave('replies')
            ->where('created_at', '<', $now->copy()->subHours(self::STALE_HOURS))
            ->count();

        if ($staleCount > 0) {
            $oldest = SupportTicket::query()
                ->where('workspace_id', $workspace->id)
                ->whereIn('status', [SupportTicket::STATUS_OPEN, SupportTicket::STATUS_IN_PROGRESS])
                ->whereDoesntHave('replies')
                ->orderBy('created_at')
                ->value('created_at');

            $oldestHours = $oldest ? (int) $oldest->diffInHours($now) : self::STALE_HOURS;

            $findings[] = new BotFinding(
                fingerprint: 'tickets_without_response',
                severity: $staleCount >= 10 ? 'critical' : 'warning',
                title: 'Support tickets waiting for a response',
                summary: sprintf(
                    '%d ticket(s) have had no reply for more than %d hours; the oldest has waited %d hours.',
                    $staleCount,
                    self::STALE_HOURS,
                    $oldestHours,
                ),
                evidence: [
                    'Open or in-progress tickets with zero replies older than '.self::STALE_HOURS.'h: '.$staleCount,
                    'Oldest unanswered ticket age: '.$oldestHours.'h',
                ],
                suggestedAction: 'Draft a courteous first response for the oldest unanswered tickets, then resolve or hand them over. Publishing any reply still needs approval in chat.',
            );
        }

        $lastAnnouncementAt = Announcement::query()
            ->where('workspace_id', $workspace->id)
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->value('created_at');

        $quietDays = $lastAnnouncementAt ? (int) $lastAnnouncementAt->diffInDays($now) : null;

        if ($quietDays === null || $quietDays >= self::QUIET_ANNOUNCEMENT_DAYS) {
            $findings[] = new BotFinding(
                fingerprint: 'no_recent_announcements',
                severity: 'info',
                title: 'No recent announcements',
                summary: $quietDays === null
                    ? 'No announcements have ever been published in this workspace.'
                    : sprintf('The last announcement was published %d days ago.', $quietDays),
                evidence: [
                    'Days since the last announcement: '.($quietDays ?? 'never'),
                ],
                suggestedAction: 'Consider drafting one announcement that matters this week; I will stage it for approval when asked.',
            );
        }

        return $findings;
    }
}
