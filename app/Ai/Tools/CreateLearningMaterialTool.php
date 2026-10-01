<?php

namespace App\Ai\Tools;

use App\Models\LearningMaterial;
use App\Models\Section;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateLearningMaterialTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'create_learning_material';
    }

    public function description(): Stringable|string
    {
        return 'Prepare creation of a learning material resource in the active workspace for human review. This tool never creates the resource directly; only the administrator approving in the UI can execute it.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        $title = trim((string) ($request['title'] ?? ''));
        if ($title === '') {
            return 'Error: learning material title is required.';
        }

        $description = trim((string) ($request['description'] ?? '')) ?: null;
        $status = in_array($request['status'] ?? '', [LearningMaterial::STATUS_DRAFT, LearningMaterial::STATUS_PUBLISHED], true)
            ? $request['status']
            : LearningMaterial::STATUS_PUBLISHED;

        $sectionIds = [];
        if (! empty($request['section_ids'])) {
            $raw = is_array($request['section_ids'])
                ? $request['section_ids']
                : explode(',', (string) $request['section_ids']);

            $sectionIds = collect($raw)
                ->map(fn ($id) => (int) trim((string) $id))
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all();
        }

        $sections = collect();
        if ($sectionIds !== []) {
            $sections = Section::query()
                ->withoutGlobalScope('workspace')
                ->whereIn('id', $sectionIds)
                ->where('workspace_id', $this->workspaceId())
                ->get();
        }

        $sectionNames = $sections->isNotEmpty()
            ? $sections->pluck('name')->implode(', ')
            : 'All enrolled students';

        $payload = [
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'section_ids' => $sectionIds,
        ];

        $preview = [
            ['field' => 'Title', 'before' => null, 'after' => $title],
            ['field' => 'Description', 'before' => null, 'after' => $description ?? 'None'],
            ['field' => 'Status', 'before' => null, 'after' => ucfirst($status)],
            ['field' => 'Target Sections', 'before' => null, 'after' => $sectionNames],
        ];

        return $this->stageAction(
            'create_learning_material',
            'Create learning material',
            "Create learning material \"{$title}\" ({$status}).",
            $payload,
            $preview,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('The title of the learning resource.')->required(),
            'description' => $schema->string()->description('Optional description or study guide notes.'),
            'status' => $schema->string()->description('Either "published" or "draft". Defaults to "published".'),
            'section_ids' => $schema->string()->description('Optional comma-separated list of section IDs to restrict access to.'),
        ];
    }
}
