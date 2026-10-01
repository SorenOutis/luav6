<?php

namespace App\Ai\Tools;

use App\Models\Section;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DeleteSectionTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'delete_section';
    }

    public function description(): Stringable|string
    {
        return 'Prepare deletion of a class section from the active workspace for human review. This tool never deletes the section directly; only the administrator approving in the UI can execute it.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        $sectionId = (int) ($request['section_id'] ?? 0);
        $section = Section::query()
            ->withoutGlobalScope('workspace')
            ->whereKey($sectionId)
            ->where('workspace_id', $this->workspaceId())
            ->withCount(['users', 'exams'])
            ->first();

        if (! $section) {
            return "Error: section with ID {$sectionId} not found in this workspace. Check workspace_overview for valid section IDs.";
        }

        $payload = [
            'section_id' => $section->id,
            'expected_updated_at' => $section->updated_at?->toJSON(),
        ];

        $preview = [
            ['field' => 'Section ID', 'before' => (string) $section->id, 'after' => 'Deleted'],
            ['field' => 'Name', 'before' => $section->name, 'after' => 'Deleted'],
            ['field' => 'Enrolled Students', 'before' => (string) $section->users_count, 'after' => 'Detached'],
            ['field' => 'Exams Linked', 'before' => (string) $section->exams_count, 'after' => 'Unassigned'],
        ];

        return $this->stageAction(
            'delete_section',
            'Delete class section',
            "Delete class section \"{$section->name}\" (ID {$section->id}) with {$section->users_count} students.",
            $payload,
            $preview,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'section_id' => $schema->integer()->description('The ID of the section to delete.')->required(),
        ];
    }
}
