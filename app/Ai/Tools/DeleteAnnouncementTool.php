<?php

namespace App\Ai\Tools;

use App\Models\Announcement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DeleteAnnouncementTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'delete_announcement';
    }

    public function description(): Stringable|string
    {
        return 'Prepare deletion of an announcement in the active workspace for human review. This tool never deletes the announcement directly; only the administrator approving in the UI can execute it.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        $announcementId = (int) ($request['announcement_id'] ?? 0);
        $announcement = Announcement::query()
            ->withoutGlobalScope('workspace')
            ->whereKey($announcementId)
            ->where('workspace_id', $this->workspaceId())
            ->first();

        if (! $announcement) {
            return "Error: announcement with ID {$announcementId} not found in this workspace.";
        }

        $payload = [
            'announcement_id' => $announcement->id,
            'expected_updated_at' => $announcement->updated_at?->toJSON(),
        ];

        $preview = [
            ['field' => 'Announcement ID', 'before' => (string) $announcement->id, 'after' => 'Deleted'],
            ['field' => 'Title', 'before' => $announcement->title, 'after' => 'Deleted'],
            ['field' => 'Body', 'before' => Str::limit($announcement->description, 60), 'after' => 'Deleted'],
        ];

        return $this->stageAction(
            'delete_announcement',
            'Delete announcement',
            "Delete announcement \"{$announcement->title}\" (ID {$announcement->id}).",
            $payload,
            $preview,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'announcement_id' => $schema->integer()->description('The ID of the announcement to delete.')->required(),
        ];
    }
}
