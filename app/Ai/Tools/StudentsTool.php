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

    public function description(): Stringable|string
    {
        return 'List or search students in the admin\'s workspace (or platform-wide for Super Admins) — name, email, sections, LSI level, streak, and recent exam average. Supports searching by name, email, or student ID. Call this tool immediately whenever the admin\'s message is just a person\'s name or part of a name (even a single word like "angeline" or "cabrera"): the search is a case-insensitive partial match over first, middle, and last name and email, and it works with any word order. When several students match, return them as options and ask which one — never pick silently.';
    }

    public function handle(Request $request): Stringable|string
    {
        $admin = auth()->user();

        if (! $admin?->is_admin) {
            return 'Only admins can use this tool.';
        }

        $search = trim((string) ($request['search'] ?? ''), " \t\n\r\0\x0B\"'");

        $applySearchFilter = function ($query) use ($search) {
            if ($search === '') {
                return;
            }

            $cleanId = ltrim($search, '#');
            $tokens = collect(preg_split('/[\s,]+/', $search, -1, PREG_SPLIT_NO_EMPTY))
                ->map(fn (string $token) => trim($token, "\"'"))
                ->filter()
                ->values();

            $query->where(function ($q) use ($tokens, $cleanId) {
                foreach ($tokens as $token) {
                    $q->where(function ($inner) use ($token) {
                        $inner->where('users.name', 'like', "%{$token}%")
                            ->orWhere('users.email', 'like', "%{$token}%")
                            ->orWhere('users.first_name', 'like', "%{$token}%")
                            ->orWhere('users.middle_name', 'like', "%{$token}%")
                            ->orWhere('users.last_name', 'like', "%{$token}%");
                    });
                }

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
            return json_encode([
                'search' => $search,
                'match_count' => 0,
                'students' => [],
                'message' => $search !== ''
                    ? "No students found matching \"{$search}\" in this workspace."
                    : 'There are no students in this workspace yet.',
            ]);
        }

        $payload = [
            'search' => $search,
            'match_count' => $students->count(),
            'students' => $students,
        ];

        if ($search !== '' && $students->count() > 1) {
            $payload['disambiguation_required'] = true;
            $payload['message'] = 'Multiple students matched. Present these options to the admin and ask which student they mean. Do not pick one silently.';
            $payload['options'] = $students->map(fn (array $row): array => [
                'id' => $row['id'],
                'label' => $row['name'].' <'.$row['email'].'>',
                'sections' => $row['sections'],
            ])->values();
        }

        return json_encode($payload);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Optional name, email, or student ID to filter students by. Partial matches are supported for single names or full names in any word order (e.g. "angeline", "cabrera", or "cabrera angeline").'),
        ];
    }
}
