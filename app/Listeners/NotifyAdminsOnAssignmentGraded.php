<?php

namespace App\Listeners;

use App\Events\AssignmentGraded;
use App\Models\Assignment;
use App\Models\User;
use App\Services\AdminNotificationService;

class NotifyAdminsOnAssignmentGraded
{
    /**
     * Handle the AssignmentGraded event.
     */
    public function handle(AssignmentGraded $event): void
    {
        $assignment = Assignment::query()->find($event->assignmentId);
        $student = User::query()->find($event->userId);

        if ($assignment) {
            $studentName = $student?->name ?? 'Student';
            $workspace = $assignment->workspace;

            AdminNotificationService::notifyAdmins(
                title: 'Assignment Graded',
                body: "Assignment '{$assignment->title}' was graded for {$studentName}.",
                workspace: $workspace,
                icon: 'heroicon-o-academic-cap',
                color: 'success',
            );
        }
    }
}
