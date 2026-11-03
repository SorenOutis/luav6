<?php

declare(strict_types=1);

namespace App\Ai\Support;

use App\Models\AiBot;
use App\Models\User;
use App\Support\WorkspaceContext;
use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * Binds the tenant and actor for queued bot work.
 *
 * WorkspaceContext and Auth are request-scoped; a queue worker has neither.
 * Jobs create this object per run and pass it explicitly. Nothing is stored in
 * a static property or singleton, so Octane/long-lived workers cannot leak one
 * job's tenant into the next.
 */
final class BotRunContext
{
    public function __construct(
        public AiBot $bot,
        public ?User $actor,
        public int $depth = 0,
        public ?int $chatSessionId = null,
        public ?int $handoffId = null,
    ) {}

    public function run(Closure $callback): mixed
    {
        if (! $this->bot->workspace_id) {
            throw new \RuntimeException('Bot run context requires a workspace-scoped bot.');
        }

        return app(WorkspaceContext::class)->run($this->bot->workspace_id, function () use ($callback): mixed {
            $guard = Auth::guard('web');
            $previous = $guard->user();

            if ($this->actor) {
                $guard->setUser($this->actor);
            }

            try {
                return $callback();
            } finally {
                if ($previous) {
                    $guard->setUser($previous);
                } else {
                    Auth::forgetGuards();
                }
            }
        });
    }
}
