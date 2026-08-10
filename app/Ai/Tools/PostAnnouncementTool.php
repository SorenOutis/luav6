<?php

namespace App\Ai\Tools;

use App\Models\Announcement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class PostAnnouncementTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Post an announcement that is immediately visible to students. IMPORTANT: present the announcement text to the admin first and only call this with confirm=true after they explicitly approve.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $admin = auth()->user();

        if (! $admin?->is_admin) {
            return 'Only admins can use this tool.';
        }

        if (! $request['confirm']) {
            return 'NOT EXECUTED — confirmation missing. Present the announcement to the admin and ask them to confirm; then call this tool again with confirm=true.';
        }

        $title = trim((string) ($request['title'] ?? ''));
        $description = trim((string) ($request['description'] ?? ''));

        if ($title === '' || $description === '') {
            return 'Error: both a title and a description are required.';
        }

        $announcement = Announcement::create([
            'title' => Str::limit($title, 255, ''),
            'description' => $description,
            'link' => trim((string) ($request['link'] ?? '')) ?: null,
            'is_active' => true,
        ]);

        return "Announcement posted: \"{$announcement->title}\" (ID {$announcement->id}). It is now visible to students on their dashboard.";
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Announcement title.')->required(),
            'description' => $schema->string()->description('Announcement body text.')->required(),
            'link' => $schema->string()->description('Optional URL attached to the announcement.'),
            'confirm' => $schema->boolean()->description('Must be true, and only after the admin explicitly approved the text.')->required(),
        ];
    }
}
