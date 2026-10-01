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

        $query = Grade::query()
            ->withoutGlobalScope('workspace')
            ->where('workspace_id', $workspaceId)
            ->with(['student:id,name,email', 'section:id,name']);

        if (! empty($request['student_id'])) {
            $query->where('user_id', (int) $request['student_id']);
        } elseif (! empty($request['student_name'])) {
            $name = trim((string) $request['student_name']);
            $query->whereHas('student', fn ($q) => $q->where('name', 'like', "%{$name}%"));
        }

        if (! empty($request['section_id'])) {
            $query->where('section_id', (int) $request['section_id']);
        }

        if (! empty($request['subject'])) {
            $subject = trim((string) $request['subject']);
            $query->where('subject', 'like', "%{$subject}%");
        }

        $limit = min(max((int) ($request['limit'] ?? 20), 1), 50);

        $grades = $query->latest('id')
            ->limit($limit)
            ->get()
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
            'section_id' => $schema->integer(),
            'subject' => $schema->string(),
            'limit' => $schema->integer(),
        ];
    }
}
