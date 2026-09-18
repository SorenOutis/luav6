<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class UserFollowedNotification extends Notification
{
    use Queueable;

    public function __construct(protected string $followerPublicId, protected string $followerName, protected ?string $followerAvatar) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        // The database copy powers the notification inbox; broadcast makes the
        // existing Pusher/Echo connection update it without a page refresh.
        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'type' => 'follow',
            'icon' => 'users',
            'title' => 'New follower',
            'message' => "{$this->followerName} started following you.",
            'meta' => 'Social',
            'image' => $this->followerAvatar,
            'href' => "/u/{$this->followerPublicId}",
        ];
    }
}
