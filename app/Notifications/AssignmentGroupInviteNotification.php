<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Group-activity invite lifecycle notification (sent / accepted / declined).
 *
 * Delivered to the database (bell dropdown) AND broadcast, so with Pusher
 * configured the bell refreshes live — AppHeader already listens for
 * BroadcastNotificationCreated on the user's private channel.
 *
 * Payload keys follow the shared header shape (type/icon/title/message/
 * meta/href) plus invite_id + assignment_id so the bell can render inline
 * Accept / Decline actions that hit the respond endpoint directly.
 */
class AssignmentGroupInviteNotification extends Notification
{
    use Queueable;

    public function __construct(protected array $payload) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }

    public function databaseType(object $notifiable): string
    {
        return 'assignment_group_invite';
    }

    public function broadcastType(): string
    {
        return 'assignment.group_invite';
    }
}
