<?php

namespace App\Ai\Tools;

use App\Models\Grade;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdateGradeTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'update_grade';
    }

    public function description(): Stringable|string
    {
        return 'Prepare an update/edit to an existing student grade record for human review. Shows before/after diff for score, max score, remarks, subject, or period.';
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

        $changes = [];
        $preview = [];

        if (isset($request['score']) && is_numeric($request['score'])) {
            $newScore = (float) $request['score'];
            if ((float) $grade->score !== $newScore) {
                $changes['score'] = $newScore;
                $preview[] = ['field' => 'Score', 'before' => (string) $grade->score, 'after' => (string) $newScore];
            }
        }

        if (isset($request['max_score']) && is_numeric($request['max_score'])) {
            $newMax = (float) $request['max_score'];
            if ($newMax <= 0) {
                return 'Error: max_score must be greater than 0.';
            }
            if ((float) $grade->max_score !== $newMax) {
                $changes['max_score'] = $newMax;
                $preview[] = ['field' => 'Max Score', 'before' => (string) $grade->max_score, 'after' => (string) $newMax];
            }
        }

        if (isset($request['remarks'])) {
            $newRemarks = trim((string) $request['remarks']) ?: null;
            if ($grade->remarks !== $newRemarks) {
                $changes['remarks'] = $newRemarks;
                $preview[] = ['field' => 'Remarks', 'before' => $grade->remarks ?? 'None', 'after' => $newRemarks ?? 'None'];
            }
        }

        if (! empty($request['subject'])) {
            $newSubject = trim((string) $request['subject']);
            if ($newSubject !== '' && $grade->subject !== $newSubject) {
                $changes['subject'] = $newSubject;
                $preview[] = ['field' => 'Subject', 'before' => $grade->subject, 'after' => $newSubject];
            }
        }

        if (! empty($request['period'])) {
            $newPeriod = trim((string) $request['period']);
            if ($newPeriod !== '' && $grade->period !== $newPeriod) {
                $changes['period'] = $newPeriod;
                $preview[] = ['field' => 'Period', 'before' => $grade->period, 'after' => $newPeriod];
            }
        }

        if ($changes === []) {
            return 'No changes were proposed for this grade record.';
        }

        $payload = [
            'grade_id' => $grade->id,
            'expected_updated_at' => $grade->updated_at?->toJSON(),
            'changes' => $changes,
        ];

        $studentName = $grade->student?->name ?? "Student #{$grade->user_id}";

        return $this->stageAction(
            'update_grade',
            'Update student grade',
            "Update grade record #{$grade->id} for {$studentName} ({$grade->subject} - {$grade->period}).",
            $payload,
            $preview,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'grade_id' => $schema->integer()->required(),
            'score' => $schema->number(),
            'max_score' => $schema->number(),
            'remarks' => $schema->string(),
            'subject' => $schema->string(),
            'period' => $schema->string(),
        ];
    }
}
