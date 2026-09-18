<?php

namespace App\Ai\Tools;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class StudentsTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'List or search students in the admin\'s workspace — name, email, sections, LSI level, streak, and recent exam average. Limited to 10 results.';
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

        $students = User::forWorkspace()
            ->where('is_admin', false)
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->with(['currentSeasonProgress', 'sections'])
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(fn (User $student) => [
                'name' => $student->name,
                'email' => $student->email,
                'sections' => $student->sections->pluck('name')->values(),
                'system_level' => $student->currentSeasonProgress?->level ?? 1,
                'streak_days' => (int) ($student->current_streak ?? 0),
                'recent_exam_average' => round(
                    (float) $student->examSubmissions()->where('status', 'graded')->latest('updated_at')->limit(5)->avg('score'),
                    1
                ),
            ])
            ->values();

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
            'search' => $schema->string()->description('Optional partial name to filter students by.'),
        ];
    }
}
