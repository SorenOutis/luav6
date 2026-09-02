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
        $startForValidation = $exam->starts_at ?? $exam->exam_date;

        if ($status = $request['status'] ?? null) {
            if (! in_array($status, ['draft', 'published', 'closed'], true)) {
                return 'Error: status must be one of draft, published, closed.';
            }
            if ($status !== $exam->status) {
                $updates['status'] = $status;
                $preview[] = ['field' => 'Status', 'before' => $exam->status, 'after' => $status];
            }
        }

        if ($dateInput = $request['starts_at'] ?? $request['exam_date'] ?? null) {
            try {
                $examDate = Carbon::parse((string) $dateInput);
            } catch (\Throwable) {
                return 'Error: starts_at must be a valid date/time, e.g. "2026-08-20 09:00".';
            }
            if (! $exam->exam_date || ! $exam->exam_date->equalTo($examDate)) {
                $updates['starts_at'] = $examDate->toIso8601String();
                $startForValidation = $examDate;
                $preview[] = [
                    'field' => 'Starts at',
                    'before' => $exam->starts_at?->format('M d, Y g:i A') ?? $exam->exam_date?->format('M d, Y g:i A'),
                    'after' => $examDate->format('M d, Y g:i A'),
                ];
            }
        }

        if (array_key_exists('ends_at', $request)) {
            if ($request['ends_at'] === '' || $request['ends_at'] === null) {
                if ($exam->ends_at !== null) {
                    $updates['ends_at'] = null;
                    $preview[] = [
                        'field' => 'Ends at',
                        'before' => $exam->ends_at?->format('M d, Y g:i A'),
                        'after' => 'Open until manually closed',
                    ];
                }
            } else {
                try {
                    $endTime = Carbon::parse((string) $request['ends_at']);
                } catch (\Throwable) {
                    return 'Error: ends_at must be a valid date/time, e.g. "2026-08-20 10:00".';
                }

                if ($startForValidation && $endTime->lte($startForValidation)) {
                    return 'Error: ends_at must be after the exam start time.';
                }

                if (! $exam->ends_at || ! $exam->ends_at->equalTo($endTime)) {
                    $updates['ends_at'] = $endTime->toIso8601String();
                    $preview[] = [
                        'field' => 'Ends at',
                        'before' => $exam->ends_at?->format('M d, Y g:i A') ?? 'Open until manually closed',
                        'after' => $endTime->format('M d, Y g:i A'),
                    ];
                }
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
            return 'Nothing to update — provide a status, starts_at/exam_date, ends_at, or duration_minutes that differs from the current value.';
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
            'starts_at' => $schema->string()->description('New start date/time, e.g. "2026-08-20 09:00".'),
            'exam_date' => $schema->string()->description('Legacy alias for starts_at, e.g. "2026-08-20 09:00".'),
            'ends_at' => $schema->string()->description('New end date/time, e.g. "2026-08-20 10:00". Use an empty string to make the exam open-ended.'),
            'duration_minutes' => $schema->integer()->description('New duration in minutes (5–600).'),
        ];
    }
}
