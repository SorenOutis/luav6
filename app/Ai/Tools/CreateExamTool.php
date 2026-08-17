<?php

namespace App\Ai\Tools;

use App\Models\Section;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateExamTool extends PendingWriteTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Prepare a new DRAFT exam for human review. This tool never writes the exam; it creates a server-issued approval card showing the exact values. Call it once after gathering the required details. The administrator must approve in the UI.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        $title = Str::limit(trim((string) ($request['title'] ?? '')), 255, '');
        if ($title === '') {
            return 'Error: a title is required.';
        }

        $sectionId = $request['section_id'] ?? null;
        $section = null;
        if ($sectionId !== null) {
            $section = Section::query()
                ->withoutGlobalScope('workspace')
                ->whereKey((int) $sectionId)
                ->where('workspace_id', $this->workspaceId())
                ->first();
            if (! $section) {
                return "Error: section [{$sectionId}] does not exist in this workspace. Use workspace_overview for valid section IDs.";
            }
        }

        try {
            $examDate = Carbon::parse((string) ($request['exam_date'] ?? ''));
        } catch (\Throwable) {
            return 'Error: exam_date must be a valid date/time, e.g. "2026-08-20 09:00".';
        }

        $duration = min(max((int) ($request['duration_minutes'] ?? 60), 5), 600);
        $description = trim((string) ($request['description'] ?? '')) ?: null;
        if ($description !== null && mb_strlen($description) > 20000) {
            return 'Error: exam description is too long (maximum 20,000 characters).';
        }
        $sectionLabel = $section ? "{$section->name} (#{$section->id})" : 'All sections';
        $payload = [
            'title' => $title,
            'description' => $description,
            'exam_date' => $examDate->toIso8601String(),
            'duration_minutes' => $duration,
            'section_id' => $section?->id,
            'section_expected_updated_at' => $section?->updated_at?->toJSON(),
        ];

        return $this->stageAction(
            'create_exam',
            'Create draft exam',
            "Create the draft exam \"{$title}\" for {$sectionLabel}.",
            $payload,
            [
                ['field' => 'Record', 'before' => null, 'after' => 'New draft exam'],
                ['field' => 'Title', 'before' => null, 'after' => $title],
                ['field' => 'Description', 'before' => null, 'after' => $description],
                ['field' => 'Date and time', 'before' => null, 'after' => $examDate->format('M d, Y g:i A')],
                ['field' => 'Duration', 'before' => null, 'after' => "{$duration} minutes"],
                ['field' => 'Section', 'before' => null, 'after' => $sectionLabel],
                ['field' => 'Status', 'before' => null, 'after' => 'draft'],
            ],
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Exam title.')->required(),
            'exam_date' => $schema->string()->description('Date and time, e.g. "2026-08-20 09:00".')->required(),
            'duration_minutes' => $schema->integer()->description('Duration in minutes (5–600, default 60).'),
            'section_id' => $schema->integer()->description('Optional section ID from workspace_overview. Omit for all sections.'),
            'description' => $schema->string()->description('Optional exam description.'),
        ];
    }
}
