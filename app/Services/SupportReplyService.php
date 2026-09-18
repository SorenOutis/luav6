<?php

namespace App\Services;

use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SupportReplyService
{
    public function __construct(private StudentNotificationService $students) {}

    /**
     * Post a reply as an admin/owner and notify the ticket owner, unless the
     * replier is the owner themselves.
     */
    public function createAdminReply(SupportTicket $ticket, User $admin, string $message): SupportTicketReply
    {
        $clean = $this->cleanMessage($message);

        if ($clean === '') {
            throw ValidationException::withMessages([
                'message' => 'The reply must contain visible text.',
            ]);
        }

        $reply = $ticket->replies()->create([
            'user_id' => $admin->id,
            'message' => $clean,
        ]);

        $this->notifyStudentOfReply($reply);

        return $reply;
    }

    /**
     * Notify the ticket owner about a reply written by someone else (an
     * admin). Replies authored by the owner never notify themselves.
     */
    public function notifyStudentOfReply(SupportTicketReply $reply): void
    {
        $ticket = $reply->ticket;
        $student = $ticket?->user;

        if (! $ticket || ! $student || (int) $student->id === (int) $reply->user_id) {
            return;
        }

        $this->students->sendSupportReply($student, $ticket);
    }

    private function cleanMessage(string $message): string
    {
        $clean = str_replace(["\r\n", "\r"], "\n", $message);
        $clean = trim(strip_tags($clean));
        $clean = (string) preg_replace("/\n{3,}/", "\n\n", $clean);

        return mb_substr($clean, 0, 2000);
    }
}
