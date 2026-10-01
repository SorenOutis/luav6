<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateCourseTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'create_course';
    }

    public function description(): Stringable|string
    {
        return 'Prepare creation of a new course in the active workspace for human review. This tool never creates the course directly; only the administrator approving in the UI can execute it.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        $name = trim((string) ($request['name'] ?? ''));
        if ($name === '') {
            return 'Error: course name is required (e.g. "Biology 101" or "Advanced Web Development").';
        }

        $description = trim((string) ($request['description'] ?? '')) ?: null;
        $totalLessons = max(1, (int) ($request['total_lessons'] ?? 1));

        $payload = [
            'name' => $name,
            'description' => $description,
            'total_lessons' => $totalLessons,
        ];

        $preview = [
            ['field' => 'Course Name', 'before' => null, 'after' => $name],
            ['field' => 'Description', 'before' => null, 'after' => $description ?? 'None'],
            ['field' => 'Estimated Lessons', 'before' => null, 'after' => (string) $totalLessons],
        ];

        return $this->stageAction(
            'create_course',
            'Create course',
            "Create course \"{$name}\" with {$totalLessons} lessons.",
            $payload,
            $preview,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('The title or name of the course.')->required(),
            'description' => $schema->string()->description('Optional course overview and description.'),
            'total_lessons' => $schema->integer()->description('Estimated total lessons (defaults to 1).'),
        ];
    }
}
