<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired at the student's private channel whenever their assignment work is
 * graded (or a grade / points / feedback revision lands). The assignments
 * page listens for it and refreshes its list in place, so new feedback shows
 * up without a manual reload.
 */
class AssignmentGraded implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly int $assignmentId,
        public readonly string $status,
        public readonly ?string $grade,
        public readonly float $points,
        public readonly float $xpEarned,
        public readonly bool $hasFeedback,
        public readonly ?string $gradedAt,
    ) {
        if (config('broadcasting.connections.pusher.key')) {
            $this->broadcastVia('pusher');
        }
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("App.Models.User.{$this->userId}"),
        ];
    }

    /**
     * Keep the payload minimal — the page reloads its own data, so only the
     * identifying bits travel over the wire.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'assignment_id' => $this->assignmentId,
            'status' => $this->status,
            'grade' => $this->grade,
            'points' => $this->points,
            'xp_earned' => $this->xpEarned,
            'has_feedback' => $this->hasFeedback,
            'graded_at' => $this->gradedAt,
        ];
    }
}
