<?php

namespace App\Ai\Tools;

use App\Models\Exam;
use App\Models\User;
use App\Services\ExamBlockService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ManageExamBlocksTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'manage_exam_blocks';
    }

    public function description(): Stringable|string
    {
        return 'Prepare changes to which students are blocked from taking a specific exam: add blocks, remove blocks, or clear all blocks. This tool never changes blocks directly; only the administrator approving in the UI can execute it.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        $workspaceId = $this->workspaceId();
        if (! $workspaceId) {
            return 'Error: no active workspace selected.';
        }

        $examId = (int) ($request['exam_id'] ?? 0);
        $examTitle = trim((string) ($request['exam_title'] ?? ''));

        if ($examId <= 0 && $examTitle === '') {
            return 'Error: provide exam_id or exam_title. Use exams_admin for valid exam IDs.';
        }

        $exam = Exam::query()
            ->withoutGlobalScope('workspace')
            ->where('exams.workspace_id', $workspaceId)
            ->when($examId > 0, fn ($query) => $query->whereKey($examId))
            ->when($examId <= 0, fn ($query) => $query->where('title', 'like', "%{$examTitle}%"))
            ->orderByDesc('exam_date')
            ->first();

        if (! $exam) {
            return 'Error: exam not found in this workspace. Use exams_admin for valid exam IDs.';
        }

        $blockIds = $this->parseIds($request['block_student_ids'] ?? null);
        $unblockIds = $this->parseIds($request['unblock_student_ids'] ?? null);
        $clearAll = filter_var($request['clear_all'] ?? false, FILTER_VALIDATE_BOOLEAN);

        foreach ($this->parseNames($request['block_students'] ?? null) as $name) {
            $resolved = $this->resolveStudentByName($name, $workspaceId);
            if (is_string($resolved)) {
                return $resolved;
            }
            $blockIds[] = $resolved->id;
        }

        foreach ($this->parseNames($request['unblock_students'] ?? null) as $name) {
            $resolved = $this->resolveStudentByName($name, $workspaceId);
            if (is_string($resolved)) {
                return $resolved;
            }
            $unblockIds[] = $resolved->id;
        }

        $blockIds = collect($blockIds)->unique()->values()->all();
        $unblockIds = collect($unblockIds)->unique()->values()->all();

        if ($blockIds !== []) {
            $validCount = User::query()
                ->whereIn('id', $blockIds)
                ->where('is_admin', false)
                ->where(function ($query) use ($workspaceId) {
                    $query->whereHas('workspaces', fn ($q) => $q->whereKey($workspaceId))
                        ->orWhereHas('sections', fn ($q) => $q->where('sections.workspace_id', $workspaceId))
                        ->orWhere('current_workspace_id', $workspaceId);
                })
                ->count();

            if ($validCount !== count($blockIds)) {
                return 'Error: one or more specified students do not exist in this workspace or are administrators.';
            }
        }

        $currentIds = app(ExamBlockService::class)->blockedUserIds($exam);

        $targetIds = $clearAll ? [] : $currentIds;

        if ($unblockIds !== []) {
            $targetIds = array_values(array_diff($targetIds, $unblockIds));
        }

        foreach ($blockIds as $addId) {
            if (! in_array($addId, $targetIds, true)) {
                $targetIds[] = $addId;
            }
        }

        sort($currentIds);
        $sortedTarget = $targetIds;
        sort($sortedTarget);

        if ($currentIds === $sortedTarget) {
            return "No changes were specified for the blocked-students list of \"{$exam->title}\" (#{$exam->id}).";
        }

        $names = User::query()
            ->whereIn('id', array_unique([...$currentIds, ...$sortedTarget]))
            ->pluck('name', 'id');

        $formatNames = function (array $ids) use ($names): string {
            $ids = collect($ids);

            return $ids->isEmpty()
                ? 'None'
                : $ids->map(fn (int $id) => $names[$id] ?? "#{$id}")->implode(', ');
        };

        $changeSummary = $clearAll
            ? 'Clear all blocked students'
            : sprintf('Block %d, unblock %d student(s)', count($blockIds), count($unblockIds));

        return $this->stageAction(
            'manage_exam_blocks',
            'Update exam blocked students',
            "{$changeSummary} for exam \"{$exam->title}\" (#{$exam->id}).",
            [
                'exam_id' => $exam->id,
                'blocked_user_ids' => $sortedTarget,
                'expected_updated_at' => $exam->updated_at?->toJSON(),
            ],
            [
                ['field' => 'Exam', 'before' => null, 'after' => "{$exam->title} (#{$exam->id})"],
                ['field' => 'Blocked students', 'before' => $formatNames($currentIds), 'after' => $formatNames($sortedTarget)],
            ],
        );
    }

    /**
     * @return array<int, int>
     */
    private function parseIds(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $items = is_array($value) ? $value : explode(',', (string) $value);

        return collect($items)
            ->map(fn ($id) => (int) trim((string) $id))
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function parseNames(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $items = is_array($value) ? $value : explode(',', (string) $value);

        return collect($items)
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->values()
            ->all();
    }

    private function resolveStudentByName(string $name, int $workspaceId): User|string
    {
        $matches = User::query()
            ->where('is_admin', false)
            ->where('name', 'like', "%{$name}%")
            ->where(function ($query) use ($workspaceId) {
                $query->whereHas('workspaces', fn ($q) => $q->whereKey($workspaceId))
                    ->orWhereHas('sections', fn ($q) => $q->where('sections.workspace_id', $workspaceId))
                    ->orWhere('current_workspace_id', $workspaceId);
            })
            ->limit(5)
            ->get();

        if ($matches->isEmpty()) {
            return "Error: student \"{$name}\" not found in this workspace. Use the students tool to find valid students.";
        }

        if ($matches->count() > 1) {
            $options = $matches->map(fn (User $user) => "{$user->name} (#{$user->id})")->implode(', ');

            return "Error: multiple students match \"{$name}\": {$options}. Use student IDs instead.";
        }

        return $matches->first();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'exam_id' => $schema->integer()->description('The ID of the exam whose blocked-student list should change.'),
            'exam_title' => $schema->string()->description('The title (or partial title) of the exam, alternative to exam_id.'),
            'block_student_ids' => $schema->string()->description('Comma-separated student IDs to add to the blocked list.'),
            'block_students' => $schema->string()->description('Comma-separated student names to block (must match exactly one workspace student each).'),
            'unblock_student_ids' => $schema->string()->description('Comma-separated student IDs to remove from the blocked list.'),
            'unblock_students' => $schema->string()->description('Comma-separated student names to unblock.'),
            'clear_all' => $schema->boolean()->description('Set true to remove every current block for this exam before applying additions.'),
        ];
    }
}
