<?php

declare(strict_types=1);

namespace App\Ai\Watchers;

use App\Models\AiBot;
use App\Models\Workspace;

/**
 * One read-only domain check that a specialist bot performs unattended.
 *
 * Implementations never write anything anywhere. They query, compare with
 * deterministic thresholds, and return zero or more candidate findings; the
 * orchestrator decides what to dedupe, post, or email.
 */
interface BotWatcherCheck
{
    /** Stable domain key for findings and team messages. */
    public function domain(): string;

    /** @return list<BotFinding> */
    public function run(AiBot $bot, Workspace $workspace): array;
}
