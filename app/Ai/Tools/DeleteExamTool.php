<?php

namespace App\Ai\Tools;

use App\Models\Exam;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DeleteExamTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'delete_exam';
    }

    public function description(): Stringable|string
    {
        return 'Prepare the deletion of an exam for human review. This tool never deletes the exam directly. It creates a server-issued approval card showing the exam details; only the administrator approving in the UI can execute the deletion.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        $examId = (int) ($request['exam_id'] ?? 0);
        $exam = Exam::query()
            ->withoutGlobalScope('workspace')
            ->whereKey($examId)
            ->where('workspace_id', $this->workspaceId())
            ->first();

        if (! $exam) {
            return 'Error: exam not found in this workspace. Use exams_admin for valid exam IDs.';
        }

        $partsCount = $exam->parts()->count();
        $submissionsCount = $exam->submissions()->count();

        $payload = [
            'exam_id' => $exam->id,
            'expected_updated_at' => $exam->updated_at?->toJSON(),
            'title' => $exam->title,
        ];

        $preview = [
            ['field' => 'Exam ID', 'before' => (string) $exam->id, 'after' => '[To be deleted]'],
            ['field' => 'Title', 'before' => $exam->title, 'after' => '[Deleted]'],
            ['field' => 'Status', 'before' => $exam->status, 'after' => '[Deleted]'],
            ['field' => 'Question Parts', 'before' => "{$partsCount} parts", 'after' => '0'],
            ['field' => 'Submissions', 'before' => "{$submissionsCount} submissions", 'after' => '0'],
        ];

        return $this->stageAction(
            'delete_exam',
            'Delete exam',
            "Delete exam #{$exam->id} (\"{$exam->title}\") with {$partsCount} parts and {$submissionsCount} submissions.",
            $payload,
            $preview,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'exam_id' => $schema->integer()->required(),
        ];
    }
}
