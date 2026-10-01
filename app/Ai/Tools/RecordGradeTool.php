<?php

namespace App\Ai\Tools;

use App\Models\Section;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RecordGradeTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'record_grade';
    }

    public function description(): Stringable|string
    {
        return 'Prepare a new grade record for a student for human review. This tool never creates the grade directly. It stages a server-issued approval card showing the student name, section, subject, grading period, score, max score, and remarks. The administrator must approve in the UI.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        $studentId = (int) ($request['student_id'] ?? 0);
        $student = User::query()->whereKey($studentId)->first();
        if (! $student) {
            return "Error: student with ID {$studentId} not found. Use the students tool to find valid student IDs.";
        }

        $sectionId = (int) ($request['section_id'] ?? 0);
        $section = Section::query()
            ->withoutGlobalScope('workspace')
            ->whereKey($sectionId)
            ->where('workspace_id', $this->workspaceId())
            ->first();

        if (! $section) {
            return "Error: section with ID {$sectionId} not found in this workspace. Use workspace_overview for valid section IDs.";
        }

        $subject = trim((string) ($request['subject'] ?? ''));
        if ($subject === '') {
            $subject = $section->name;
        }

        $period = trim((string) ($request['period'] ?? 'Midterm'));
        if ($period === '') {
            $period = 'Midterm';
        }

        if (! isset($request['score']) || ! is_numeric($request['score'])) {
            return 'Error: score is required and must be a number.';
        }

        $score = (float) $request['score'];
        $maxScore = isset($request['max_score']) && is_numeric($request['max_score'])
            ? (float) $request['max_score']
            : 100.0;

        if ($maxScore <= 0) {
            return 'Error: max_score must be greater than 0.';
        }

        $remarks = trim((string) ($request['remarks'] ?? '')) ?: null;
        $pct = round(($score / $maxScore) * 100, 1);

        $payload = [
            'student_id' => $student->id,
            'section_id' => $section->id,
            'subject' => $subject,
            'period' => $period,
            'score' => $score,
            'max_score' => $maxScore,
            'remarks' => $remarks,
        ];

        $preview = [
            ['field' => 'Student', 'before' => null, 'after' => "{$student->name} (#{$student->id})"],
            ['field' => 'Section', 'before' => null, 'after' => "{$section->name} (#{$section->id})"],
            ['field' => 'Subject', 'before' => null, 'after' => $subject],
            ['field' => 'Grading Period', 'before' => null, 'after' => $period],
            ['field' => 'Score', 'before' => null, 'after' => "{$score} / {$maxScore} ({$pct}%)"],
            ['field' => 'Remarks', 'before' => null, 'after' => $remarks ?? 'None'],
        ];

        return $this->stageAction(
            'record_grade',
            'Record student grade',
            "Record {$subject} ({$period}) grade of {$score}/{$maxScore} for {$student->name}.",
            $payload,
            $preview,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'student_id' => $schema->integer()->required(),
            'section_id' => $schema->integer()->required(),
            'subject' => $schema->string()->required(),
            'period' => $schema->string()->required(),
            'score' => $schema->number()->required(),
            'max_score' => $schema->number(),
            'remarks' => $schema->string(),
        ];
    }
}
