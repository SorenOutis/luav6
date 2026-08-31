<?php

namespace App\Observers;

use App\Models\Submission;
use App\Services\AdminNotificationService;
use Throwable;

class SubmissionObserver
{
    /**
     * Handle the Submission "created" event.
     */
    public function created(Submission $submission): void
    {
        try {
            $studentName = $submission->user?->name ?? 'A student';
            $assignment = $submission->assignment_id ? $submission->assignment()->withoutGlobalScope('workspace')->first() : null;
            $assignmentTitle = $assignment?->title ?? 'an assignment';
            $workspace = $assignment ? AdminNotificationService::resolveWorkspace($assignment) : $submission->user;

            AdminNotificationService::notifyAdmins(
                title: 'Assignment Submitted',
                body: "{$studentName} submitted assignment '{$assignmentTitle}'.",
                workspace: $workspace,
                icon: 'heroicon-o-clipboard-document-check',
                color: 'info',
            );
        } catch (Throwable) {
        }
    }
}
