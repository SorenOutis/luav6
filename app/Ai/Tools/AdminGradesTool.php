<?php

namespace App\Ai\Tools;

use App\Models\Grade;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AdminGradesTool implements Tool
{
    public function name(): string
    {
        return 'grades_admin';
    }

    public function description(): Stringable|string
    {
        return 'Inspect recorded grades for students in the admin\'s workspace. Can filter by student ID, student name, section ID, or subject.';
    }

    public function handle(Request $request): Stringable|string
    {
        $admin = auth()->user();
        if (! $admin?->is_admin) {
            return 'Only admins can use this tool.';
        }

        $workspaceId = app(WorkspaceContext::class)->id();
        if (! $workspaceId) {
            return 'Error: no active workspace selected.';
        }

        $studentEmail = ! empty($request['student_email']) ? trim((string) $request['student_email']) : null;
        $studentName = ! empty($request['student_name']) ? trim((string) $request['student_name']) : null;
        $studentId = ! empty($request['student_id']) ? (int) $request['student_id'] : null;

        $applyFilters = function ($q) use ($studentId, $studentName, $studentEmail, $request) {
            if ($studentId) {
                $q->where('user_id', $studentId);
            } elseif ($studentName || $studentEmail) {
                $q->whereHas('student', function ($sub) use ($studentName, $studentEmail) {
                    if ($studentEmail) {
                        $sub->where('email', 'like', "%{$studentEmail}%");
                    }
                    if ($studentName) {
                        $sub->where(fn ($s) => $s->where('name', 'like', "%{$studentName}%")->orWhere('email', 'like', "%{$studentName}%"));
                    }
                });
            }

            if (! empty($request['section_id'])) {
                $q->where('section_id', (int) $request['section_id']);
            }

            if (! empty($request['subject'])) {
                $subject = trim((string) $request['subject']);
                $q->where('subject', 'like', "%{$subject}%");
            }
        };

        $query = Grade::query()
            ->withoutGlobalScope('workspace')
            ->where('workspace_id', $workspaceId)
            ->with(['student:id,name,email', 'section:id,name']);

        $applyFilters($query);

        $limit = min(max((int) ($request['limit'] ?? 20), 1), 50);

        $gradeModels = $query->latest('id')->limit($limit)->get();

        if ($gradeModels->isEmpty() && ($studentId || $studentName || $studentEmail) && $admin->isSuperAdmin()) {
            $fallbackQuery = Grade::query()
                ->withoutGlobalScope('workspace')
                ->with(['student:id,name,email', 'section:id,name']);
            $applyFilters($fallbackQuery);
            $gradeModels = $fallbackQuery->latest('id')->limit($limit)->get();
        }

        $grades = $gradeModels
            ->map(fn (Grade $grade) => [
                'id' => $grade->id,
                'student_id' => $grade->user_id,
                'student_name' => $grade->student?->name ?? 'Unknown',
                'section_id' => $grade->section_id,
                'section_name' => $grade->section?->name ?? 'Unknown',
                'subject' => $grade->subject,
                'period' => $grade->period,
                'score' => (float) $grade->score,
                'max_score' => (float) $grade->max_score,
                'percentage' => $grade->percentage,
                'remarks' => $grade->remarks,
                'recorded_at' => $grade->created_at?->format('M d, Y'),
            ])
            ->values();

        if ($grades->isEmpty()) {
            return 'No matching student grades found in this workspace.';
        }

        return json_encode($grades);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'student_id' => $schema->integer(),
            'student_name' => $schema->string(),
            'student_email' => $schema->string(),
            'section_id' => $schema->integer(),
            'subject' => $schema->string(),
            'limit' => $schema->integer(),
        ];
    }
}
