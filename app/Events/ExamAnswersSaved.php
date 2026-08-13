<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExamAnswersSaved implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets, SerializesModels;

    /**
     * @param  list<int>  $questionNumbers
     */
    public function __construct(
        public readonly int $userId,
        public readonly int $examId,
        public readonly int $examPartId,
        public readonly array $questionNumbers,
        public readonly int $answeredCount,
        public readonly string $savedAt,
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
            new PrivateChannel("exam.{$this->examId}.student.{$this->userId}"),
        ];
    }

    /**
     * Keep answer text out of Pusher payloads. The WebSocket event is a durable
     * save acknowledgement; the answer itself remains in the database.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'exam_id' => $this->examId,
            'exam_part_id' => $this->examPartId,
            'question_numbers' => $this->questionNumbers,
            'answered_count' => $this->answeredCount,
            'saved_at' => $this->savedAt,
        ];
    }
}
