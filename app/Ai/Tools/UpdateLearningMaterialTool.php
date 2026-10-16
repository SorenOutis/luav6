<?php

namespace App\Ai\Tools;

use App\Models\LearningMaterial;
use App\Models\Section;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdateLearningMaterialTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'update_learning_material';
    }

    public function description(): Stringable|string
    {
        return 'Prepare updates to an existing learning material in the active workspace for human review. This tool never updates the material directly; only the administrator approving in the UI can execute it.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        $materialId = (int) ($request['material_id'] ?? 0);
        $material = LearningMaterial::query()
            ->withoutGlobalScope('workspace')
            ->whereKey($materialId)
            ->where('workspace_id', $this->workspaceId())
            ->with('sections')
            ->first();

        if (! $material) {
            return "Error: learning material with ID {$materialId} not found in this workspace. Check materials_admin for valid material IDs.";
        }

        $changes = [];
        $preview = [];

        if (isset($request['title']) && trim((string) $request['title']) !== '') {
            $newTitle = trim((string) $request['title']);
            if ($newTitle !== $material->title) {
                $changes['title'] = $newTitle;
                $preview[] = ['field' => 'Title', 'before' => $material->title, 'after' => $newTitle];
            }
        }

        if (isset($request['description'])) {
            $newDesc = trim((string) $request['description']) ?: null;
            if ($newDesc !== $material->description) {
                $changes['description'] = $newDesc;
                $preview[] = ['field' => 'Description', 'before' => $material->description ?? 'None', 'after' => $newDesc ?? 'None'];
            }
        }

        if (isset($request['status'])) {
            $newStatus = trim((string) $request['status']);
            if (in_array($newStatus, [LearningMaterial::STATUS_DRAFT, LearningMaterial::STATUS_PUBLISHED], true) && $newStatus !== $material->status) {
                $changes['status'] = $newStatus;
                $preview[] = ['field' => 'Status', 'before' => ucfirst($material->status), 'after' => ucfirst($newStatus)];
            }
        }

        if (isset($request['section_ids'])) {
            $raw = is_array($request['section_ids'])
                ? $request['section_ids']
                : explode(',', (string) $request['section_ids']);

            $newSectionIds = collect($raw)
                ->map(fn ($id) => (int) trim((string) $id))
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            $currentSectionIds = $material->sections->pluck('id')->sort()->values()->all();
            $sortedNew = collect($newSectionIds)->sort()->values()->all();

            if ($currentSectionIds !== $sortedNew) {
                $sections = Section::query()
                    ->withoutGlobalScope('workspace')
                    ->whereIn('id', $newSectionIds)
                    ->where('workspace_id', $this->workspaceId())
                    ->get();

                if ($sections->count() !== count($newSectionIds)) {
                    return 'Error: one or more specified sections do not exist in this workspace.';
                }

                $beforeNames = $material->sections->pluck('name')->implode(', ') ?: 'All enrolled students';
                $afterNames = $sections->pluck('name')->implode(', ') ?: 'All enrolled students';

                $changes['section_ids'] = $newSectionIds;
                $preview[] = ['field' => 'Target Sections', 'before' => $beforeNames, 'after' => $afterNames];
            }
        }

        if ($changes === []) {
            return "No changes were specified for learning material \"{$material->title}\" (#{$material->id}).";
        }

        $payload = [
            'material_id' => $material->id,
            'changes' => $changes,
            'expected_updated_at' => $material->updated_at?->toJSON(),
        ];

        return $this->stageAction(
            'update_learning_material',
            'Update learning material',
            "Update learning material \"{$material->title}\" (#{$material->id}).",
            $payload,
            $preview,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'material_id' => $schema->integer()->description('The ID of the learning material to update.')->required(),
            'title' => $schema->string()->description('Optional updated title of the learning resource.'),
            'description' => $schema->string()->description('Optional updated description or study guide notes.'),
            'status' => $schema->string()->description('Optional updated status: "published" or "draft".'),
            'section_ids' => $schema->string()->description('Optional comma-separated list of section IDs to restrict access to. Pass an empty string to make it visible to all enrolled students.'),
        ];
    }
}
