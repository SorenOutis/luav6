<?php

namespace App\Ai\Tools;

use App\Models\Grade;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DeleteGradeTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'delete_grade';
    }

    public function description(): Stringable|string
    {
        return 'Prepare the deletion of a recorded student grade for human review. This tool never deletes the record directly; only the administrator approving in the UI can execute it.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        $gradeId = (int) ($request['grade_id'] ?? 0);
        $grade = Grade::query()
            ->withoutGlobalScope('workspace')
            ->whereKey($gradeId)
            ->where('workspace_id', $this->workspaceId())
            ->with(['student:id,name', 'section:id,name'])
            ->first();

        if (! $grade) {
            return "Error: grade with ID {$gradeId} not found in this workspace. Use grades_admin to find valid grade IDs.";
        }

        $studentName = $grade->student?->name ?? "Student #{$grade->user_id}";

        $payload = [
            'grade_id' => $grade->id,
            'expected_updated_at' => $grade->updated_at?->toJSON(),
        ];

        $preview = [
            ['field' => 'Grade ID', 'before' => (string) $grade->id, 'after' => '[To be deleted]'],
            ['field' => 'Student', 'before' => $studentName, 'after' => '[Deleted]'],
            ['field' => 'Subject', 'before' => $grade->subject, 'after' => '[Deleted]'],
            ['field' => 'Period', 'before' => $grade->period, 'after' => '[Deleted]'],
            ['field' => 'Score', 'before' => "{$grade->score} / {$grade->max_score}", 'after' => '[Deleted]'],
        ];

        return $this->stageAction(
            'delete_grade',
            'Delete grade record',
            "Delete grade record #{$grade->id} for {$studentName} ({$grade->subject} - {$grade->period}).",
            $payload,
            $preview,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'grade_id' => $schema->integer()->required(),
        ];
    }
}
