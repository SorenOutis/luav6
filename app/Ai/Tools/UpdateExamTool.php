<?php

namespace App\Ai\Tools;

use App\Models\Exam;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdateExamTool extends PendingWriteTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Prepare an exam status, schedule, or duration change for human review. This tool never updates the exam. It creates a server-issued card with the exact before/after diff; only a UI approval can execute it.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        $exam = Exam::query()
            ->withoutGlobalScope('workspace')
            ->whereKey((int) ($request['exam_id'] ?? 0))
            ->where('workspace_id', $this->workspaceId())
            ->first();
        if (! $exam) {
            return 'Error: exam not found in this workspace. Use exams_admin for valid exam IDs.';
        }

        $updates = [];
        $preview = [];

        if ($status = $request['status'] ?? null) {
            if (! in_array($status, ['draft', 'published', 'closed'], true)) {
                return 'Error: status must be one of draft, published, closed.';
            }
            if ($status !== $exam->status) {
                $updates['status'] = $status;
                $preview[] = ['field' => 'Status', 'before' => $exam->status, 'after' => $status];
            }
        }

        if ($dateInput = $request['exam_date'] ?? null) {
            try {
                $examDate = Carbon::parse((string) $dateInput);
            } catch (\Throwable) {
                return 'Error: exam_date must be a valid date/time, e.g. "2026-08-20 09:00".';
            }
            if (! $exam->exam_date || ! $exam->exam_date->equalTo($examDate)) {
                $updates['exam_date'] = $examDate->toIso8601String();
                $preview[] = [
                    'field' => 'Date and time',
                    'before' => $exam->exam_date?->format('M d, Y g:i A'),
                    'after' => $examDate->format('M d, Y g:i A'),
                ];
            }
        }

        if (($durationInput = $request['duration_minutes'] ?? null) !== null) {
            $duration = min(max((int) $durationInput, 5), 600);
            if ($duration !== (int) $exam->duration_minutes) {
                $updates['duration_minutes'] = $duration;
                $preview[] = [
                    'field' => 'Duration',
                    'before' => "{$exam->duration_minutes} minutes",
                    'after' => "{$duration} minutes",
                ];
            }
        }

        if ($updates === []) {
            return 'Nothing to update — provide a status, exam_date, or duration_minutes that differs from the current value.';
        }

        return $this->stageAction(
            'update_exam',
            'Update exam',
            "Update \"{$exam->title}\" (exam #{$exam->id}).",
            [
                'exam_id' => $exam->id,
                'expected_updated_at' => $exam->updated_at?->toJSON(),
                'changes' => $updates,
            ],
            $preview,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'exam_id' => $schema->integer()->description('Exam ID from exams_admin.')->required(),
            'status' => $schema->string()->description('New status: draft, published, or closed.'),
            'exam_date' => $schema->string()->description('New date/time, e.g. "2026-08-20 09:00".'),
            'duration_minutes' => $schema->integer()->description('New duration in minutes (5–600).'),
        ];
    }
}
