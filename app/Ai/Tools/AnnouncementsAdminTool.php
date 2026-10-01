<?php

namespace App\Ai\Tools;

use App\Models\Announcement;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AnnouncementsAdminTool implements Tool
{
    public function name(): string
    {
        return 'announcements_admin';
    }

    public function description(): Stringable|string
    {
        return 'List announcements in the active workspace with ID, title, description preview, target section, and creation date.';
    }

    public function handle(Request $request): Stringable|string
    {
        $admin = auth()->user();
        if (! $admin?->is_admin) {
            return 'Only admins can use this tool.';
        }

        $workspaceId = app(WorkspaceContext::class)->id();

        $announcements = Announcement::query()
            ->withoutGlobalScope('workspace')
            ->where('workspace_id', $workspaceId)
            ->with('section:id,name')
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (Announcement $announcement) => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'description' => $announcement->description,
                'section' => $announcement->section?->name ?? 'All sections',
                'created_at' => $announcement->created_at?->format('M d, Y'),
            ])
            ->values();

        if ($announcements->isEmpty()) {
            return 'There are no announcements in this workspace yet. You can post one using post_announcement.';
        }

        return (string) json_encode($announcements);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
