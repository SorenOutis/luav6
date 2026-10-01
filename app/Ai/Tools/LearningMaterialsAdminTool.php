<?php

namespace App\Ai\Tools;

use App\Models\LearningMaterial;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class LearningMaterialsAdminTool implements Tool
{
    public function name(): string
    {
        return 'materials_admin';
    }

    public function description(): Stringable|string
    {
        return 'List learning materials in the active workspace with ID, title, description, category, and publication status.';
    }

    public function handle(Request $request): Stringable|string
    {
        $admin = auth()->user();
        if (! $admin?->is_admin) {
            return 'Only admins can use this tool.';
        }

        $workspaceId = app(WorkspaceContext::class)->id();

        $materials = LearningMaterial::query()
            ->withoutGlobalScope('workspace')
            ->where('workspace_id', $workspaceId)
            ->with('category:id,name')
            ->orderBy('title')
            ->get()
            ->map(fn (LearningMaterial $material) => [
                'id' => $material->id,
                'title' => $material->title,
                'description' => $material->description,
                'category' => $material->category?->name,
                'status' => $material->status,
                'view_count' => $material->view_count,
            ])
            ->values();

        if ($materials->isEmpty()) {
            return 'There are no learning materials in this workspace yet. You can create one using create_learning_material.';
        }

        return (string) json_encode($materials);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
