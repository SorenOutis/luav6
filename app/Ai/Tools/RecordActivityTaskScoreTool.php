<?php

namespace App\Ai\Tools;

use App\Models\ActivityTask;
use App\Models\Section;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RecordActivityTaskScoreTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'record_activity_task_score';
    }

    public function description(): Stringable|string
    {
        return 'Prepare recording or updating a student score for one or more class activity tasks (e.g. recitation, lab activity, project, additional points) for human review. This tool never creates or modifies scores directly. It stages a server-issued approval card showing the student name, tasks, scores, and remarks. The administrator must approve in the UI.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        $targetWorkspaceId = $this->workspaceId();

        $specifiedSection = null;
        $sectionId = (int) ($request['section_id'] ?? 0);
        if ($sectionId > 0) {
            $specifiedSection = Section::query()
                ->withoutGlobalScope('workspace')
                ->whereKey($sectionId)
                ->where('workspace_id', $targetWorkspaceId)
                ->first();
        } elseif (! empty($request['section_name'])) {
            $sectionName = trim((string) $request['section_name']);
            $specifiedSection = Section::query()
                ->withoutGlobalScope('workspace')
                ->where('workspace_id', $targetWorkspaceId)
                ->where(function ($q) use ($sectionName) {
                    $q->where('name', $sectionName)->orWhere('name', 'like', "%{$sectionName}%");
                })
                ->first();
        }

        $allStudents = ! empty($request['all_students'])
            || in_array(strtolower(trim((string) ($request['student_name'] ?? ''))), ['all', 'all students', 'roster'], true)
            || in_array(strtolower(trim((string) ($request['student_id'] ?? ''))), ['all'], true);

        $student = null;

        if ($allStudents) {
            $section = $specifiedSection;
            if (! $section) {
                $taskId = (int) ($request['task_id'] ?? 0);
                $taskTitle = trim((string) ($request['task_title'] ?? ''));
                if ($taskId > 0) {
                    $section = ActivityTask::query()
                        ->withoutGlobalScope('workspace')
                        ->whereKey($taskId)
                        ->where('workspace_id', $targetWorkspaceId)
                        ->first()?->section;
                } elseif ($taskTitle !== '') {
                    $section = ActivityTask::query()
                        ->withoutGlobalScope('workspace')
                        ->where('workspace_id', $targetWorkspaceId)
                        ->where('title', 'like', "%{$taskTitle}%")
                        ->first()?->section;
                }
            }

            if (! $section) {
                return 'Error: specify section_id or section_name to record scores for all section students.';
            }

            if (! isset($request['score']) || ! is_numeric($request['score'])) {
                return 'Error: score is required and must be a number.';
            }

            $score = (float) $request['score'];
            if ($score < 0) {
                return 'Error: score cannot be negative.';
            }

            $students = User::query()
                ->whereHas('sections', fn ($q) => $q->where('sections.id', $section->id))
                ->where('is_admin', false)
                ->orderBy('name')
                ->get();

            if ($students->isEmpty()) {
                return "Error: no students are enrolled in section {$section->name}.";
            }
        } else {
            if ($specifiedSection) {
                $student = User::query()
                    ->whereHas('sections', fn ($q) => $q->where('sections.id', $specifiedSection->id))
                    ->where(function ($q) use ($request) {
                        if (! empty($request['student_id']) && is_numeric($request['student_id'])) {
                            $q->orWhere('users.id', (int) $request['student_id']);
                        }
                        if (! empty($request['student_email'])) {
                            $q->orWhere('users.email', strtolower(trim((string) $request['student_email'])));
                        }
                        if (! empty($request['student_name'])) {
                            $name = trim((string) $request['student_name']);
                            $q->orWhere('users.name', $name)->orWhere('users.name', 'like', "%{$name}%");
                        }
                    })
                    ->first();
            }

            if (! $student) {
                $student = $this->findWorkspaceUser(
                    $request['student_id'] ?? null,
                    $request['student_name'] ?? null,
                    $request['student_email'] ?? null,
                );
            }

            if (! $student) {
                $student = $this->resolveUserByIdentifiers(
                    $request['student_id'] ?? null,
                    $request['student_name'] ?? null,
                    $request['student_email'] ?? null,
                );
            }

            if (! $student) {
                return 'Error: student not found. Provide a valid student_id, student_name, or student_email.';
            }

            if ($specifiedSection && ! $student->sections()->where('sections.id', $specifiedSection->id)->exists()) {
                return "Error: student {$student->name} is not enrolled in section {$specifiedSection->name}.";
            }

            if (! isset($request['score']) || ! is_numeric($request['score'])) {
                return 'Error: score is required and must be a number.';
            }

            $score = (float) $request['score'];
            if ($score < 0) {
                return 'Error: score cannot be negative.';
            }
        }

        $tasksQuery = ActivityTask::query()
            ->withoutGlobalScope('workspace')
            ->where('workspace_id', $targetWorkspaceId)
            ->with(['section:id,name', 'scores' => fn ($q) => ! empty($student) ? $q->where('user_id', $student->id) : $q]);

        if ($specifiedSection) {
            $tasksQuery->where('section_id', $specifiedSection->id);
        }

        $taskId = (int) ($request['task_id'] ?? 0);
        $taskIds = ! empty($request['task_ids']) && is_array($request['task_ids'])
            ? array_map('intval', $request['task_ids'])
            : [];
        $taskTitle = trim((string) ($request['task_title'] ?? ''));

        if ($taskId > 0) {
            $tasks = $tasksQuery->whereKey($taskId)->get();
        } elseif (! empty($taskIds)) {
            $tasks = $tasksQuery->whereIn('id', $taskIds)->get();
        } elseif ($taskTitle !== '') {
            $tasks = $tasksQuery->where('title', 'like', "%{$taskTitle}%")->get();
        } else {
            return 'Error: task_id, task_ids, or task_title is required to identify the activity task(s).';
        }

        if ($tasks->isEmpty()) {
            return 'Error: no matching activity tasks found in this workspace'.($section ? " for section {$section->name}." : '. Check activity_tasks_admin for valid tasks.');
        }

        $remarks = trim((string) ($request['remarks'] ?? '')) ?: null;

        if ($allStudents) {
            $firstTask = $tasks->first();
            $maxPoints = (float) $firstTask->max_points;
            $pct = $maxPoints > 0 ? round(($score / $maxPoints) * 100, 1) : 0;

            $preview = [
                ['field' => 'Section', 'before' => null, 'after' => "{$section->name} (#{$section->id})"],
                ['field' => 'Task', 'before' => null, 'after' => "{$firstTask->title} (Max {$maxPoints} pts)"],
                ['field' => 'Students', 'before' => null, 'after' => "All {$students->count()} enrolled students"],
                ['field' => 'Score', 'before' => null, 'after' => "{$score} / {$maxPoints} ({$pct}%)"],
            ];

            if ($remarks) {
                $preview[] = ['field' => 'Remarks', 'before' => null, 'after' => $remarks];
            }

            $studentItems = $students->map(fn ($st) => [
                'student_id' => $st->id,
                'student_name' => $st->name,
                'score' => $score,
                'remarks' => $remarks,
            ])->all();

            $payload = [
                'activity_task_id' => $firstTask->id,
                'section_id' => $section->id,
                'score' => $score,
                'remarks' => $remarks,
                'all_students' => true,
                'students' => $studentItems,
            ];

            $summary = "Record score of {$score}/{$maxPoints} for all {$students->count()} students in section {$section->name} on \"{$firstTask->title}\".";

            return $this->stageAction(
                'record_activity_task_score',
                'Record activity task score (All students)',
                $summary,
                $payload,
                $preview,
                $targetWorkspaceId,
            );
        }

        $items = [];
        $preview = [
            ['field' => 'Student', 'before' => null, 'after' => "{$student->name} (#{$student->id})"],
        ];
        if ($specifiedSection) {
            $preview[] = ['field' => 'Section', 'before' => null, 'after' => $specifiedSection->name];
        }

        foreach ($tasks as $task) {
            $existingScore = $task->scores->first();
            $maxPoints = (float) $task->max_points;
            $pct = $maxPoints > 0 ? round(($score / $maxPoints) * 100, 1) : 0;

            $beforeText = $existingScore ? "{$existingScore->score} / {$maxPoints}" : 'Not scored';
            $afterText = "{$score} / {$maxPoints} ({$pct}%)";

            $taskLabel = $task->section ? "{$task->title} ({$task->section->name})" : $task->title;

            $preview[] = [
                'field' => $taskLabel,
                'before' => $beforeText,
                'after' => $afterText,
            ];

            $items[] = [
                'activity_task_id' => $task->id,
                'task_title' => $task->title,
                'section_name' => $task->section?->name,
                'score' => $score,
                'max_points' => $maxPoints,
                'remarks' => $remarks,
            ];
        }

        if ($remarks) {
            $preview[] = ['field' => 'Remarks', 'before' => null, 'after' => $remarks];
        }

        $firstTask = $tasks->first();
        $payload = [
            'student_id' => $student->id,
            'section_id' => $specifiedSection?->id ?? $firstTask->section_id,
            'activity_task_id' => $firstTask->id,
            'score' => $score,
            'remarks' => $remarks,
            'items' => $items,
        ];

        $summary = $tasks->count() === 1
            ? "Record score of {$score}/{$firstTask->max_points} for {$student->name} on \"{$firstTask->title}\"."
            : "Record scores ({$score} pts each) for {$student->name} on {$tasks->count()} activity tasks.";

        return $this->stageAction(
            'record_activity_task_score',
            'Record activity task score',
            $summary,
            $payload,
            $preview,
            $targetWorkspaceId,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'score' => $schema->number()->description('Score received by the student (or students).')->required(),
            'student_id' => $schema->integer()->description('Student user ID (optional if student_name or student_email provided).'),
            'student_name' => $schema->string()->description('Optional student name if ID is not known, or "all" to grade all students in the section.'),
            'student_email' => $schema->string()->description('Optional student email if ID is not known.'),
            'section_id' => $schema->integer()->description('Optional class section ID to target a specific section for student or task.'),
            'section_name' => $schema->string()->description('Optional class section name (e.g. "BSIT 1-A", "Grade 11 - STEM Einstein").'),
            'task_id' => $schema->integer()->description('Optional activity task ID if known.'),
            'task_title' => $schema->string()->description('Optional activity task title (e.g. "Performance Task 1", "Laboratory 1"). Matches tasks in this workspace.'),
            'task_ids' => $schema->array()->description('Optional array of activity task IDs when scoring multiple tasks at once.'),
            'all_students' => $schema->boolean()->description('Set to true to record the score for all enrolled students in the section.'),
            'remarks' => $schema->string()->description('Optional score remarks or feedback.'),
        ];
    }
}
