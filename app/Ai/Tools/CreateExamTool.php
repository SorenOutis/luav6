<?php

namespace App\Ai\Tools;

use App\Models\Exam;
use App\Models\Section;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateExamTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Create a new DRAFT exam in the admin\'s workspace. IMPORTANT: present the full exam summary to the admin first and only call this with confirm=true after they explicitly approve.';
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

        if (! $request['confirm']) {
            return 'NOT EXECUTED — confirmation missing. Present the exam summary to the admin and ask them to confirm; then call this tool again with confirm=true.';
        }

        $title = trim((string) ($request['title'] ?? ''));

        if ($title === '') {
            return 'Error: a title is required.';
        }

        $sectionId = $request['section_id'] ?? null;

        if ($sectionId !== null && ! Section::query()->whereKey((int) $sectionId)->exists()) {
            return "Error: section [{$sectionId}] does not exist in this workspace. Use the workspace_overview tool to list valid section IDs.";
        }

        try {
            $examDate = Carbon::parse((string) ($request['exam_date'] ?? ''));
        } catch (\Throwable) {
            return 'Error: exam_date must be a valid date/time, e.g. "2026-08-20 09:00".';
        }

        $exam = Exam::create([
            'title' => Str::limit($title, 255, ''),
            'description' => trim((string) ($request['description'] ?? '')) ?: null,
            'exam_date' => $examDate,
            'duration_minutes' => min(max((int) ($request['duration_minutes'] ?? 60), 5), 600),
            'status' => 'draft',
            'section_id' => $sectionId !== null ? (int) $sectionId : null,
        ]);

        return "Draft exam created: \"{$exam->title}\" (ID {$exam->id}) on {$examDate->format('M d, Y g:i A')}, {$exam->duration_minutes} minutes"
            .($exam->section_id ? '' : ', all sections')
            .'. It is a DRAFT with no question parts yet — add parts in the Exams panel (or via the AI Question Generator), then publish it with the update_exam tool.';
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Exam title.')->required(),
            'exam_date' => $schema->string()->description('Date and time of the exam, e.g. "2026-08-20 09:00".')->required(),
            'duration_minutes' => $schema->integer()->description('Duration in minutes (5–600, default 60).'),
            'section_id' => $schema->integer()->description('Optional section ID from workspace_overview. Omit for all sections.'),
            'description' => $schema->string()->description('Optional exam description.'),
            'confirm' => $schema->boolean()->description('Must be true, and only after the admin explicitly approved the summary.')->required(),
        ];
    }
}
