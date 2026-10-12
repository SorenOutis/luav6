<?php

namespace App\Ai\Tools;

use App\Models\ActivityTask;
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

        $student = $this->findWorkspaceUser(
            $request['student_id'] ?? null,
            $request['student_name'] ?? null,
            $request['student_email'] ?? null,
        );

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

        if (! isset($request['score']) || ! is_numeric($request['score'])) {
            return 'Error: score is required and must be a number.';
        }

        $score = (float) $request['score'];
        if ($score < 0) {
            return 'Error: score cannot be negative.';
        }

        $targetWorkspaceId = $this->workspaceId() ?: $this->resolveUserWorkspaceId($student);

        $tasksQuery = ActivityTask::query()
            ->withoutGlobalScope('workspace')
            ->where('workspace_id', $targetWorkspaceId)
            ->with(['section:id,name', 'scores' => fn ($q) => $q->where('user_id', $student->id)]);

        $sectionId = (int) ($request['section_id'] ?? 0);
        if ($sectionId > 0) {
            $tasksQuery->where('section_id', $sectionId);
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
            return 'Error: no matching activity tasks found in this workspace. Check activity_tasks_admin for valid tasks.';
        }

        $remarks = trim((string) ($request['remarks'] ?? '')) ?: null;

        $items = [];
        $preview = [
            ['field' => 'Student', 'before' => null, 'after' => "{$student->name} (#{$student->id})"],
        ];

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
            'score' => $schema->number()->description('Score received by the student.')->required(),
            'student_id' => $schema->integer()->description('Student user ID (optional if student_name or student_email provided).'),
            'student_name' => $schema->string()->description('Optional student name if ID is not known.'),
            'student_email' => $schema->string()->description('Optional student email if ID is not known.'),
            'task_id' => $schema->integer()->description('Optional activity task ID if known.'),
            'task_title' => $schema->string()->description('Optional activity task title (e.g. "Additional Points"). Matches tasks in this workspace.'),
            'task_ids' => $schema->array()->description('Optional array of activity task IDs when scoring multiple tasks at once.'),
            'section_id' => $schema->integer()->description('Optional class section ID to filter activity tasks when multiple sections have the same task title.'),
            'remarks' => $schema->string()->description('Optional score remarks or feedback.'),
        ];
    }
}
