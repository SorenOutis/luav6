<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\AiBot;
use App\Models\AiBotMemory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Proposes a durable bot fact for human approval.
 *
 * A bot may never write its own memory directly. The staged action always
 * inherits the acting administrator as the memory owner, so a bot cannot widen
 * its own visibility; only an administrator can promote a fact to shared.
 */
class RememberBotMemory extends PendingWriteTool implements Tool
{
    private const MAX_CONTENT_LENGTH = 2000;

    public function name(): string
    {
        return 'remember_bot_memory';
    }

    public function description(): Stringable|string
    {
        return 'Propose a durable fact for this bot to remember across future conversations. '
            .'The fact stays private to the current administrator and is only saved after a human approves the card. '
            .'Use it for stable preferences, recurring decisions, or workspace conventions, never for transient task state.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        if (! $this->botId) {
            return 'Error: memory can only be proposed from a bot conversation.';
        }

        $workspaceId = $this->workspaceId();
        if (! $workspaceId) {
            return 'Error: no active workspace selected.';
        }

        $content = trim(strip_tags((string) ($request['content'] ?? '')));
        if (mb_strlen($content) < 3) {
            return 'Error: content is required.';
        }
        if (mb_strlen($content) > self::MAX_CONTENT_LENGTH) {
            return 'Error: content is too long (maximum '.self::MAX_CONTENT_LENGTH.' characters).';
        }

        $importance = max(1, min(5, (int) ($request['importance'] ?? 2)));

        $bot = AiBot::query()
            ->withoutGlobalScope('workspace')
            ->whereKey($this->botId)
            ->where('workspace_id', $workspaceId)
            ->where('is_active', true)
            ->first();

        if (! $bot) {
            return 'Error: this bot is inactive or no longer belongs to the workspace.';
        }

        $actor = auth()->user();

        return $this->stageAction(
            'remember_bot_memory',
            "Remember for {$bot->name}",
            $content,
            [
                'bot_id' => $bot->id,
                'user_id' => $actor->id,
                'content' => $content,
                'importance' => $importance,
                'source' => AiBotMemory::SOURCE_APPROVED,
            ],
            [
                ['field' => 'Memory', 'before' => null, 'after' => $content],
                ['field' => 'Visibility', 'before' => null, 'after' => "Private to {$actor->name}"],
                ['field' => 'Importance', 'before' => null, 'after' => (string) $importance],
            ],
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'content' => $schema->string()
                ->description('The durable fact to remember. Keep it short and self-contained.')
                ->required(),
            'importance' => $schema->integer()
                ->description('1 = critical, 2 = useful (default), 3-5 = stored but not injected yet.'),
        ];
    }
}
