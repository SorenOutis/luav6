<?php

namespace App\Observers;

use App\Models\ExamSubmission;
use App\Services\AdminNotificationService;

class ExamSubmissionObserver
{
    /**
     * Handle the ExamSubmission "created" event.
     */
    public function created(ExamSubmission $submission): void
    {
        $studentName = $submission->user?->name ?? 'A student';
        $examTitle = $submission->exam?->title ?? 'an exam';
        $workspace = $submission->exam?->workspace ?? $submission->user?->currentWorkspace;

        AdminNotificationService::notifyAdmins(
            title: 'Exam Submitted',
            body: "{$studentName} submitted exam '{$examTitle}'.",
            workspace: $workspace,
            icon: 'heroicon-o-document-check',
            color: 'success'
        );
    }
}
