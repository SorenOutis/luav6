<?php

namespace App\Ai\Tools;

use App\Models\Exam;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdateExamTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Update an exam in the admin\'s workspace — change its status (draft/published/closed), reschedule it, or change the duration. IMPORTANT: present the changes to the admin first and only call this with confirm=true after they explicitly approve.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $admin = auth()->user();

        if (! $admin?->is_admin) {
            return 'Only admins can use this tool.';
        }

        if (! filter_var($request['confirm'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return 'NOT EXECUTED — confirmation missing. Present the planned changes to the admin and ask them to confirm; then call this tool again with confirm=true.';
        }

        // The workspace global scope makes this null for exams owned by
        // another admin.
        $exam = Exam::query()->find((int) ($request['exam_id'] ?? 0));

        if (! $exam) {
            return 'Error: exam not found in this workspace. Use the exams_admin tool to list valid exam IDs.';
        }

        $changes = [];

        if ($status = $request['status'] ?? null) {
            if (! in_array($status, ['draft', 'published', 'closed'], true)) {
                return 'Error: status must be one of draft, published, closed.';
            }

            $exam->status = $status;
            $changes[] = "status → {$status}";
        }

        if ($examDate = $request['exam_date'] ?? null) {
            try {
                $exam->exam_date = Carbon::parse((string) $examDate);
                $changes[] = 'date → '.$exam->exam_date->format('M d, Y g:i A');
            } catch (\Throwable) {
                return 'Error: exam_date must be a valid date/time, e.g. "2026-08-20 09:00".';
            }
        }

        if ($duration = $request['duration_minutes'] ?? null) {
            $exam->duration_minutes = min(max((int) $duration, 5), 600);
            $changes[] = "duration → {$exam->duration_minutes} minutes";
        }

        if ($changes === []) {
            return 'Nothing to update — provide a new status, exam_date, or duration_minutes.';
        }

        $exam->save();

        return "Exam \"{$exam->title}\" (ID {$exam->id}) updated: ".implode('; ', $changes).'.';
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'exam_id' => $schema->integer()->description('Exam ID from the exams_admin tool.')->required(),
            'status' => $schema->string()->description('New status: draft, published, or closed.'),
            'exam_date' => $schema->string()->description('New date/time, e.g. "2026-08-20 09:00".'),
            'duration_minutes' => $schema->integer()->description('New duration in minutes (5–600).'),
            'confirm' => $schema->boolean()->description('Must be true, and only after the admin explicitly approved the summary.')->required(),
        ];
    }
}
