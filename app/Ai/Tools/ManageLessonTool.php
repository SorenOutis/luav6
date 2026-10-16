<?php

namespace App\Ai\Tools;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ManageLessonTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'manage_lesson';
    }

    public function description(): Stringable|string
    {
        return 'Prepare creating or updating a course lesson (title, content, video URL, sort order, target module) inside a course module. This tool never writes directly; only the administrator approving in the UI can execute it.';
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

        $operation = trim((string) ($request['operation'] ?? ''));
        if (! in_array($operation, ['create', 'update'], true)) {
            return 'Error: operation must be "create" or "update".';
        }

        return $operation === 'create'
            ? $this->prepareCreate($request, $workspaceId)
            : $this->prepareUpdate($request, $workspaceId);
    }

    private function prepareCreate(Request $request, int $workspaceId): Stringable|string
    {
        $module = $this->resolveModule($request, $workspaceId);
        if (is_string($module)) {
            return $module;
        }

        $title = trim((string) ($request['title'] ?? ''));
        if ($title === '') {
            return 'Error: lesson title is required.';
        }

        $content = array_key_exists('content', $request->all()) ? trim((string) $request['content']) : '';
        $videoUrl = trim((string) ($request['video_url'] ?? ''));

        $sortOrder = (int) ($request['sort_order'] ?? 0);
        if ($sortOrder <= 0) {
            $sortOrder = ((int) Lesson::query()
                ->withoutGlobalScope('workspace')
                ->where('course_module_id', $module->id)
                ->max('sort_order')) + 1;
        }

        $preview = [
            ['field' => 'Lesson title', 'before' => null, 'after' => $title],
            ['field' => 'Module', 'before' => null, 'after' => "{$module->title} (#{$module->id})"],
            ['field' => 'Content', 'before' => null, 'after' => $content !== '' ? $content : 'None'],
            ['field' => 'Video URL', 'before' => null, 'after' => $videoUrl !== '' ? $videoUrl : 'None'],
            ['field' => 'Sort order', 'before' => null, 'after' => (string) $sortOrder],
        ];

        return $this->stageAction(
            'manage_lesson',
            'Create lesson',
            "Create lesson \"{$title}\" in module \"{$module->title}\" (#{$module->id}).",
            [
                'operation' => 'create',
                'attributes' => [
                    'course_module_id' => $module->id,
                    'title' => $title,
                    'content' => $content !== '' ? $content : null,
                    'video_url' => $videoUrl !== '' ? $videoUrl : null,
                    'sort_order' => $sortOrder,
                ],
            ],
            $preview,
        );
    }

    private function prepareUpdate(Request $request, int $workspaceId): Stringable|string
    {
        $lesson = $this->resolveLesson($request, $workspaceId);
        if (is_string($lesson)) {
            return $lesson;
        }

        $changes = [];
        $preview = [
            ['field' => 'Lesson', 'before' => "{$lesson->title} (#{$lesson->id})", 'after' => null],
        ];

        $title = trim((string) ($request['title'] ?? ''));
        if ($title !== '' && $title !== $lesson->title) {
            $changes['title'] = $title;
            $preview[] = ['field' => 'Title', 'before' => $lesson->title, 'after' => $title];
        }

        if (array_key_exists('content', $request->all())) {
            $content = trim((string) $request['content']) ?: null;
            if ($content !== $lesson->content) {
                $changes['content'] = $content;
                $preview[] = ['field' => 'Content', 'before' => $lesson->content ?? 'None', 'after' => $content ?? 'None'];
            }
        }

        if (array_key_exists('video_url', $request->all())) {
            $videoUrl = trim((string) $request['video_url']) ?: null;
            if ($videoUrl !== $lesson->video_url) {
                $changes['video_url'] = $videoUrl;
                $preview[] = ['field' => 'Video URL', 'before' => $lesson->video_url ?? 'None', 'after' => $videoUrl ?? 'None'];
            }
        }

        if (array_key_exists('sort_order', $request->all())) {
            $sortOrder = (int) $request['sort_order'];
            if ($sortOrder !== (int) $lesson->sort_order) {
                $changes['sort_order'] = $sortOrder;
                $preview[] = ['field' => 'Sort order', 'before' => (string) $lesson->sort_order, 'after' => (string) $sortOrder];
            }
        }

        if (! empty($request['module_id']) || ! empty($request['module_title'])) {
            $module = $this->resolveModule($request, $workspaceId);
            if (is_string($module)) {
                return $module;
            }

            if ($module->id !== $lesson->course_module_id) {
                $changes['course_module_id'] = $module->id;
                $preview[] = ['field' => 'Module', 'before' => $lesson->module?->title ?? 'Unknown', 'after' => "{$module->title} (#{$module->id})"];
            }
        }

        if ($changes === []) {
            return "No changes were specified for lesson \"{$lesson->title}\" (#{$lesson->id}).";
        }

        return $this->stageAction(
            'manage_lesson',
            'Update lesson',
            "Update lesson \"{$lesson->title}\" (#{$lesson->id}).",
            [
                'operation' => 'update',
                'lesson_id' => $lesson->id,
                'changes' => $changes,
                'expected_updated_at' => $lesson->updated_at?->toJSON(),
            ],
            $preview,
        );
    }

    private function resolveModule(Request $request, int $workspaceId): CourseModule|string
    {
        $moduleId = (int) ($request['module_id'] ?? 0);
        $moduleTitle = trim((string) ($request['module_title'] ?? ''));
        $courseId = (int) ($request['course_id'] ?? 0);
        $courseTitle = trim((string) ($request['course_title'] ?? ''));

        if ($moduleId > 0) {
            $module = CourseModule::query()
                ->withoutGlobalScope('workspace')
                ->where('workspace_id', $workspaceId)
                ->whereKey($moduleId)
                ->first();

            return $module ?: 'Error: course module not found in this workspace.';
        }

        if ($moduleTitle === '') {
            return 'Error: provide module_id or module_title (with course_id/course_title if the module name is not unique).';
        }

        $query = CourseModule::query()
            ->withoutGlobalScope('workspace')
            ->where('workspace_id', $workspaceId)
            ->where('title', 'like', "%{$moduleTitle}%");

        if ($courseId > 0) {
            $query->where('course_id', $courseId);
        } elseif ($courseTitle !== '') {
            $courseIds = Course::query()
                ->withoutGlobalScope('workspace')
                ->where('workspace_id', $workspaceId)
                ->where('name', 'like', "%{$courseTitle}%")
                ->pluck('id');
            $query->whereIn('course_id', $courseIds);
        }

        $matches = $query->limit(5)->get();

        if ($matches->isEmpty()) {
            return "Error: no course module matches \"{$moduleTitle}\" in this workspace.";
        }

        if ($matches->count() > 1) {
            $options = $matches->map(fn (CourseModule $module) => "{$module->title} (#{$module->id})")->implode(', ');

            return "Error: multiple modules match \"{$moduleTitle}\": {$options}. Use module_id instead.";
        }

        return $matches->first();
    }

    private function resolveLesson(Request $request, int $workspaceId): Lesson|string
    {
        $lessonId = (int) ($request['lesson_id'] ?? 0);
        $lessonTitle = trim((string) ($request['lesson_title'] ?? ''));

        if ($lessonId <= 0 && $lessonTitle === '') {
            return 'Error: provide lesson_id or lesson_title.';
        }

        $query = Lesson::query()
            ->withoutGlobalScope('workspace')
            ->where('workspace_id', $workspaceId)
            ->when($lessonId > 0, fn ($q) => $q->whereKey($lessonId))
            ->when($lessonId <= 0, fn ($q) => $q->where('title', 'like', "%{$lessonTitle}%"));

        $matches = $query->limit(5)->get();

        if ($matches->isEmpty()) {
            return 'Error: lesson not found in this workspace.';
        }

        if ($lessonId <= 0 && $matches->count() > 1) {
            $options = $matches->map(fn (Lesson $lesson) => "{$lesson->title} (#{$lesson->id})")->implode(', ');

            return "Error: multiple lessons match \"{$lessonTitle}\": {$options}. Use lesson_id instead.";
        }

        return $matches->first();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'operation' => $schema->string()->description('"create" or "update".')->required(),
            'module_id' => $schema->integer()->description('Target course module ID (required for create unless module_title is given).'),
            'module_title' => $schema->string()->description('Target module title (or partial), alternative to module_id.'),
            'course_id' => $schema->integer()->description('Optional course ID to disambiguate the module by title.'),
            'course_title' => $schema->string()->description('Optional course title to disambiguate the module by title.'),
            'lesson_id' => $schema->integer()->description('Lesson ID for update.'),
            'lesson_title' => $schema->string()->description('Lesson title (or partial) for update, alternative to lesson_id.'),
            'title' => $schema->string()->description('Lesson title (required for create; optional rename for update).'),
            'content' => $schema->string()->description('Lesson body content.'),
            'video_url' => $schema->string()->description('Optional video URL. Pass an empty string to clear it on update.'),
            'sort_order' => $schema->integer()->description('Position within the module (defaults to the end on create).'),
        ];
    }
}
