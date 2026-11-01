<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Jobs\RunAiBotHandoff;
use App\Models\AiBot;
use App\Models\AiBotHandoff;
use App\Models\ChatSession;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Async delegation: queue work for a specialist and return immediately.
 *
 * The target's reply lands in the transcript later as a chat message authored
 * by that bot. This tool never returns the target's answer inline.
 */
class DelegateToBot implements Tool
{
    public const MAX_PENDING_HANDOFFS = 50;

    /** @param array<int, string> $allowedTargets */
    public function __construct(
        private readonly AiBot $fromBot,
        private readonly ?User $actor,
        private readonly ?ChatSession $session = null,
        private readonly int $depth = 0,
        private readonly ?int $handoffId = null,
        private readonly array $allowedTargets = [],
    ) {}

    public function name(): string
    {
        return 'delegate_to_bot';
    }

    public function description(): Stringable|string
    {
        return 'Queue a self-contained task for a specialist bot. Use this when the answer can arrive later. '
            .'The tool returns only a handoff reference; the specialist reply is delivered as a separate message. '
            .'Every write the specialist proposes still needs human approval.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $this->fromBot->isCoordinator()) {
            return 'Error: only the Chief of Staff can delegate work.';
        }

        if (! $this->actor?->is_admin) {
            return 'Error: delegation requires an administrator actor.';
        }

        if ($this->depth >= 2) {
            return 'Error: delegation depth limit reached. Complete the task directly.';
        }

        $slug = trim((string) ($request['bot'] ?? ''));
        $task = trim((string) ($request['task'] ?? ''));

        if ($slug === '' || $task === '') {
            return 'Error: bot and task are required.';
        }

        if (mb_strlen($task) > 4000) {
            return 'Error: task is too long (maximum 4,000 characters).';
        }

        if ($this->allowedTargets !== [] && ! in_array($slug, $this->allowedTargets, true)) {
            return "Error: {$slug} is not in this coordinator's delegation roster.";
        }

        $target = AiBot::query()
            ->withoutGlobalScope('workspace')
            ->where('workspace_id', $this->fromBot->workspace_id)
            ->where('slug', $slug)
            ->where('kind', AiBot::KIND_SPECIALIST)
            ->where('is_active', true)
            ->first();

        if (! $target) {
            return "Error: no active specialist named {$slug} exists in this workspace.";
        }

        $pending = AiBotHandoff::query()
            ->withoutGlobalScope('workspace')
            ->where('workspace_id', $this->fromBot->workspace_id)
            ->pending()
            ->count();

        if ($pending >= self::MAX_PENDING_HANDOFFS) {
            return 'Error: the handoff queue is full. Finish or cancel pending work first.';
        }

        $handoff = AiBotHandoff::query()->create([
            'workspace_id' => $this->fromBot->workspace_id,
            'from_bot_id' => $this->fromBot->id,
            'requested_by_user_id' => $this->actor->id,
            'to_bot_id' => $target->id,
            'chat_session_id' => $this->session?->id,
            'parent_handoff_id' => $this->handoffId,
            'task' => $task,
            'status' => AiBotHandoff::STATUS_QUEUED,
            'depth' => $this->depth + 1,
        ]);

        RunAiBotHandoff::dispatch($handoff->id);

        return "Handoff queued to {$target->name}. Reference #{$handoff->id}. "
            .'The specialist reply will appear in this conversation when it finishes.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'bot' => $schema->string()
                ->description('The specialist slug, e.g. "registrar".')
                ->required(),
            'task' => $schema->string()
                ->description('A self-contained task. The specialist cannot see this conversation.')
                ->required(),
        ];
    }
}
