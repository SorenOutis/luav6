<?php

namespace App\Listeners;

use App\Events\AssignmentGraded;
use App\Models\Assignment;
use App\Models\User;
use App\Services\AdminNotificationService;
use Throwable;

class NotifyAdminsOnAssignmentGraded
{
    /**
     * Handle the AssignmentGraded event.
     */
    public function handle(AssignmentGraded $event): void
    {
        try {
            if (! $event->assignmentId || ! $event->userId) {
                return;
            }

            $assignment = Assignment::withoutGlobalScope('workspace')->find($event->assignmentId);
            $user = User::find($event->userId);

            if (! $assignment || ! $user) {
                return;
            }

            $studentName = $user->name ?? 'A student';
            $assignmentTitle = $assignment->title ?? 'an assignment';
            $workspace = AdminNotificationService::resolveWorkspace($assignment);

            AdminNotificationService::notifyAdmins(
                title: 'Assignment Graded',
                body: "Assignment '{$assignmentTitle}' for {$studentName} was graded.",
                workspace: $workspace,
                icon: 'heroicon-o-check-circle',
                color: 'success',
            );
        } catch (Throwable) {
        }
    }
}
