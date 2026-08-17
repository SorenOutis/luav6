<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class PostAnnouncementTool extends PendingWriteTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Prepare an active announcement for human review. This tool does not post it. It creates a server-issued approval card containing the exact title, body, link, and visibility; only a UI approval can publish it.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        $title = Str::limit(trim((string) ($request['title'] ?? '')), 255, '');
        $description = trim((string) ($request['description'] ?? ''));
        $link = trim((string) ($request['link'] ?? '')) ?: null;

        if ($title === '' || $description === '') {
            return 'Error: both a title and a description are required.';
        }
        if (mb_strlen($description) > 10000) {
            return 'Error: announcement body is too long (maximum 10,000 characters).';
        }

        return $this->stageAction(
            'post_announcement',
            'Post announcement',
            "Post \"{$title}\" immediately to student dashboards.",
            [
                'title' => $title,
                'description' => $description,
                'link' => $link,
            ],
            [
                ['field' => 'Record', 'before' => null, 'after' => 'New announcement'],
                ['field' => 'Title', 'before' => null, 'after' => $title],
                ['field' => 'Body', 'before' => null, 'after' => $description],
                ['field' => 'Link', 'before' => null, 'after' => $link],
                ['field' => 'Visible to students', 'before' => null, 'after' => 'Yes, immediately'],
            ],
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Announcement title.')->required(),
            'description' => $schema->string()->description('Announcement body text.')->required(),
            'link' => $schema->string()->description('Optional URL attached to the announcement.'),
        ];
    }
}
