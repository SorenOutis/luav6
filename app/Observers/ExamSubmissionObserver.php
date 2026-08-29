<?php

namespace App\Observers;

use App\Models\ExamSubmission;
use App\Services\AdminNotificationService;
use Throwable;

class ExamSubmissionObserver
{
    /**
     * Handle the ExamSubmission "created" event.
     */
    public function created(ExamSubmission $submission): void
    {
        try {
            $studentName = $submission->user?->name ?? 'A student';
            $exam = $submission->exam_id ? $submission->exam()->withoutGlobalScope('workspace')->first() : null;
            $examTitle = $exam?->title ?? 'an exam';
            $workspace = $exam ? AdminNotificationService::resolveWorkspace($exam) : $submission->user;

            AdminNotificationService::notifyAdmins(
                title: 'Exam Submitted',
                body: "{$studentName} submitted exam '{$examTitle}'.",
                workspace: $workspace,
                icon: 'heroicon-o-document-check',
                color: 'success',
            );
        } catch (Throwable) {
        }
    }
}
