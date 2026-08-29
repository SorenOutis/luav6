<?php

namespace App\Observers;

use App\Models\Submission;
use App\Services\AdminNotificationService;

class SubmissionObserver
{
    /**
     * Handle the Submission "created" event.
     */
    public function created(Submission $submission): void
    {
        $studentName = $submission->user?->name ?? 'A student';
        $assignment = $submission->assignment;
        $assignmentTitle = $assignment?->title ?? 'an assignment';
        $workspace = $assignment ? AdminNotificationService::resolveWorkspace($assignment) : $submission->user;

        AdminNotificationService::notifyAdmins(
            title: 'Assignment Submitted',
            body: "{$studentName} submitted assignment '{$assignmentTitle}'.",
            workspace: $workspace,
            icon: 'heroicon-o-clipboard-document-check',
            color: 'info',
        );
    }
}
