<?php

declare(strict_types=1);

namespace App\Ai\Watchers;

use App\Models\AiBot;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

/**
 * Phrases a watcher bot's reasoning for the team room.
 *
 * Deliberately minimal: no tools, no conversation, no memory. The directive
 * (privacy-safe finding data) arrives through prompt(); the reply is a
 * first-person note for the internal team room. Any provider failure falls
 * back to deterministic text, so an outage can never block a watcher run.
 */
class WatcherThinker implements Agent
{
    use Promptable;

    public function __construct(
        public readonly AiBot $bot,
        public readonly string $directive,
    ) {}

    public function instructions(): string
    {
        return $this->bot->compiledInstructions()
            ."\n\nWATCHER MODE\n"
            .'You are performing an unattended read-only domain check. You have no tools and cannot complete any action. '
            .'Reply with ONE short first-person team note (at most 3 sentences): what you found, what you suggest, '
            .'and that you will wait for approval before anything is sent, published, or changed. '
            .'Never invent numbers beyond the data provided, and never mention message contents or private identity details.';
    }
}
