<?php

namespace App\Ai\Tools;

use App\Models\LearningMaterial;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DeleteLearningMaterialTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'delete_learning_material';
    }

    public function description(): Stringable|string
    {
        return 'Prepare deletion of a learning material resource from the active workspace for human review. This tool never deletes the resource directly; only the administrator approving in the UI can execute it.';
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
            ->first();

        if (! $material) {
            return "Error: learning material with ID {$materialId} not found in this workspace. Check materials_admin for valid IDs.";
        }

        $payload = [
            'material_id' => $material->id,
            'expected_updated_at' => $material->updated_at?->toJSON(),
        ];

        $preview = [
            ['field' => 'Resource ID', 'before' => (string) $material->id, 'after' => 'Deleted'],
            ['field' => 'Title', 'before' => $material->title, 'after' => 'Deleted'],
            ['field' => 'Status', 'before' => ucfirst($material->status), 'after' => 'Deleted'],
        ];

        return $this->stageAction(
            'delete_learning_material',
            'Delete learning material',
            "Delete learning material \"{$material->title}\" (ID {$material->id}).",
            $payload,
            $preview,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'material_id' => $schema->integer()->description('The ID of the learning material to delete.')->required(),
        ];
    }
}
