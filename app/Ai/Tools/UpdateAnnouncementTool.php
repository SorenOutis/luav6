<?php

namespace App\Ai\Tools;

use App\Models\Announcement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdateAnnouncementTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'update_announcement';
    }

    public function description(): Stringable|string
    {
        return 'Prepare updates to an existing announcement in the active workspace for human review. This tool never updates the announcement directly; only the administrator approving in the UI can execute it.';
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
            return "Error: announcement with ID {$announcementId} not found in this workspace. Check announcements_admin for valid IDs.";
        }

        $changes = [];
        $preview = [];

        if (isset($request['title']) && trim((string) $request['title']) !== '') {
            $newTitle = Str::limit(trim((string) $request['title']), 255, '');
            if ($newTitle !== $announcement->title) {
                $changes['title'] = $newTitle;
                $preview[] = ['field' => 'Title', 'before' => $announcement->title, 'after' => $newTitle];
            }
        }

        if (isset($request['description']) && trim((string) $request['description']) !== '') {
            $newDesc = trim((string) $request['description']);
            if ($newDesc !== $announcement->description) {
                $changes['description'] = $newDesc;
                $preview[] = ['field' => 'Body', 'before' => Str::limit($announcement->description, 60), 'after' => Str::limit($newDesc, 60)];
            }
        }

        if (array_key_exists('link', $request->all())) {
            $newLink = trim((string) ($request['link'] ?? '')) ?: null;
            if ($newLink !== $announcement->link) {
                $changes['link'] = $newLink;
                $preview[] = ['field' => 'Link', 'before' => $announcement->link ?? 'None', 'after' => $newLink ?? 'None'];
            }
        }

        if ($changes === []) {
            return "No changes were specified for announcement \"{$announcement->title}\" (#{$announcement->id}).";
        }

        $payload = [
            'announcement_id' => $announcement->id,
            'changes' => $changes,
            'expected_updated_at' => $announcement->updated_at?->toJSON(),
        ];

        return $this->stageAction(
            'update_announcement',
            'Update announcement',
            "Update announcement \"{$announcement->title}\" (#{$announcement->id}).",
            $payload,
            $preview,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'announcement_id' => $schema->integer()->description('The ID of the announcement to update.')->required(),
            'title' => $schema->string()->description('Optional updated title.'),
            'description' => $schema->string()->description('Optional updated body text.'),
            'link' => $schema->string()->description('Optional updated link/URL.'),
        ];
    }
}
