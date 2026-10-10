<?php

namespace App\Ai\Tools;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class StudentsTool implements Tool
{
    public function name(): string
    {
        return 'students';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'List or search students in the admin\'s workspace (or platform-wide for Super Admins) — name, email, sections, LSI level, streak, and recent exam average. Supports searching by name, email, or student ID.';
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

        $search = trim((string) ($request['search'] ?? ''));

        $applySearchFilter = function ($query) use ($search) {
            if ($search === '') {
                return;
            }
            $cleanId = ltrim($search, '#');
            $query->where(function ($q) use ($search, $cleanId) {
                $q->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('users.first_name', 'like', "%{$search}%")
                    ->orWhere('users.last_name', 'like', "%{$search}%");

                if (is_numeric($cleanId)) {
                    $q->orWhere('users.id', (int) $cleanId);
                }
            });
        };

        $mapStudent = fn (User $student) => [
            'id' => $student->id,
            'name' => $student->name,
            'email' => $student->email,
            'workspace' => $student->workspaces->first()?->name ?? 'Active Workspace',
            'sections' => $student->sections->pluck('name')->values(),
            'system_level' => $student->currentSeasonProgress?->level ?? 1,
            'streak_days' => (int) ($student->current_streak ?? 0),
            'recent_exam_average' => round(
                (float) $student->examSubmissions()->where('status', 'graded')->latest('updated_at')->limit(5)->avg('score'),
                1
            ),
        ];

        $studentModels = User::forWorkspace()
            ->where('is_admin', false)
            ->where($applySearchFilter)
            ->with(['currentSeasonProgress', 'sections', 'workspaces'])
            ->orderBy('name')
            ->limit(25)
            ->get();

        if ($studentModels->isEmpty() && $search !== '' && $admin->isSuperAdmin()) {
            $studentModels = User::query()
                ->where('is_admin', false)
                ->where($applySearchFilter)
                ->with(['currentSeasonProgress', 'sections', 'workspaces'])
                ->orderBy('name')
                ->limit(25)
                ->get();
        }

        $students = $studentModels->map($mapStudent)->values();

        if ($students->isEmpty()) {
            return $search !== ''
                ? "No students found matching \"{$search}\" in this workspace."
                : 'There are no students in this workspace yet.';
        }

        return json_encode($students);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Optional name, email, or student ID to filter students by.'),
        ];
    }
}
