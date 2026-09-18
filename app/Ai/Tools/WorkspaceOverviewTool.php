<?php

namespace App\Ai\Tools;

use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamSubmission;
use App\Models\Section;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class WorkspaceOverviewTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Get an overview of the admin\'s workspace — student/section/exam counts, submissions waiting for grading, and the section/course IDs needed by other tools. All data is limited to the admin\'s own workspace.';
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

        return json_encode([
            'students' => User::forWorkspace()->where('is_admin', false)->count(),
            'sections' => Section::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Section $section) => ['id' => $section->id, 'name' => $section->name])
                ->values(),
            'courses' => Course::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Course $course) => ['id' => $course->id, 'name' => $course->name])
                ->values(),
            'exams' => [
                'total' => Exam::count(),
                'draft' => Exam::where('status', 'draft')->count(),
                'published' => Exam::where('status', 'published')->count(),
                'closed' => Exam::where('status', 'closed')->count(),
            ],
            'submissions_pending_grading' => ExamSubmission::query()
                ->where(fn ($query) => $query->whereIn('status', ['pending_ai', 'pending_review'])->orWhere('grading_failed', true))
                ->whereHas('exam')
                ->count(),
        ]);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
