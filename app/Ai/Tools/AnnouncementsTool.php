<?php

namespace App\Ai\Tools;

use App\Models\Announcement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AnnouncementsTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Get the latest active school announcements (title, description, link, and when posted).';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $announcements = Announcement::query()
            ->where('is_active', true)
            ->latest()
            ->limit(5)
            ->get(['title', 'description', 'link', 'created_at'])
            ->map(fn (Announcement $announcement) => [
                'title' => $announcement->title,
                'description' => $announcement->description,
                'link' => $announcement->link,
                'posted' => $announcement->created_at?->diffForHumans(),
            ])
            ->values();

        if ($announcements->isEmpty()) {
            return 'There are no active announcements right now.';
        }

        return json_encode($announcements);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
