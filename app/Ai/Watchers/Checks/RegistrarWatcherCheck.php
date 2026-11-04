<?php

declare(strict_types=1);

namespace App\Ai\Watchers\Checks;

use App\Ai\Watchers\BotFinding;
use App\Ai\Watchers\BotWatcherCheck;
use App\Models\AiBot;
use App\Models\Course;
use App\Models\User;
use App\Models\Workspace;

/**
 * Registrar: onboarding and enrolment basics should not stall at zero.
 */
class RegistrarWatcherCheck implements BotWatcherCheck
{
    public const NEVER_LOGGED_IN_CRITICAL = 10;

    public function domain(): string
    {
        return 'enrolment';
    }

    public function run(AiBot $bot, Workspace $workspace): array
    {
        $findings = [];

        $members = function () use ($workspace) {
            return User::query()
                ->where(function ($query) use ($workspace) {
                    $query->whereHas('workspaces', fn ($q) => $q->whereKey($workspace->id))
                        ->orWhereHas('sections', fn ($q) => $q->where('sections.workspace_id', $workspace->id))
                        ->orWhere('current_workspace_id', $workspace->id);
                })
                ->where('is_admin', false);
        };

        $neverLoggedIn = $members()->whereNull('last_login_at')->count();
        $students = $members()->count();

        if ($neverLoggedIn > 0) {
            $findings[] = new BotFinding(
                fingerprint: 'members_never_logged_in',
                severity: $neverLoggedIn >= self::NEVER_LOGGED_IN_CRITICAL ? 'critical' : 'warning',
                title: 'Students who never logged in',
                summary: sprintf(
                    '%d of %d workspace students have never logged in since enrolment.',
                    $neverLoggedIn,
                    $students,
                ),
                evidence: [
                    'Students without any login: '.$neverLoggedIn,
                    'Total workspace students: '.$students,
                ],
                suggestedAction: 'Review whether welcome instructions, passwords, or sections need a nudge. Any campaign message must be approved before sending.',
            );
        }

        $emptyCourses = Course::query()
            ->where('workspace_id', $workspace->id)
            ->whereDoesntHave('modules')
            ->withCount('users')
            ->get();

        if ($emptyCourses->isNotEmpty()) {
            $promoted = $emptyCourses->filter(fn ($course) => $course->users_count > 0)->count();

            $findings[] = new BotFinding(
                fingerprint: 'courses_without_modules',
                severity: $promoted > 0 ? 'warning' : 'info',
                title: 'Courses still without modules',
                summary: sprintf(
                    '%d course(s) have no modules or lessons%s.',
                    $emptyCourses->count(),
                    $promoted > 0 ? ", and {$promoted} of them already have enrolled students" : '',
                ),
                evidence: [
                    'Courses without modules: '.$emptyCourses->count(),
                    'Courses without modules that already have students: '.$promoted,
                ],
                suggestedAction: 'Outline an initial module plan for the empty courses and stage the first lesson draft; creating content needs approval.',
            );
        }

        return $findings;
    }
}
