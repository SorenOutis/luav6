<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Contracts\RequiresReplyGuard;
use App\Ai\Support\BotPolicy;
use App\Ai\Support\ToolRegistry;
use App\Ai\Tools\DelegateToBot;
use App\Models\AiBot;
use App\Models\ChatSession;
use App\Models\Setting;
use App\Models\User;
use App\Support\AiBotsGate;
use App\Support\PlatformKnowledge;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Laravel\Ai\Tools\AgentTool;
use Stringable;

/**
 * The Chief of Staff: a read-only coordinator that delegates to specialists.
 *
 * Sync delegation mounts one AgentTool per specialist for answers needed inside
 * this turn. Async delegation mounts DelegateToBot for work that outlives the
 * turn. A specialist BotAgent never mounts either, so delegation cannot recurse
 * through the sync path.
 */
class CoordinatorAgent implements Agent, Conversational, HasTools, RequiresReplyGuard
{
    use Promptable;

    /**
     * @param  array<int, AiBot>  $roster
     * @param  array<int, Message>  $history
     */
    public function __construct(
        public AiBot $coordinator,
        public array $roster = [],
        public ?User $actor = null,
        public ?ChatSession $session = null,
        public array $history = [],
        public string $userContext = '',
        public int $depth = 0,
    ) {}

    public function instructions(): Stringable|string
    {
        $instructions = $this->coordinator->compiledInstructions();
        $instructions .= "\n\nTEAM ROSTER:\n";

        foreach ($this->roster as $bot) {
            $instructions .= "- {$bot->name} (`{$bot->slug}`): ".($bot->title ?: 'workspace specialist')."\n";
        }

        $instructions .= "\nCOORDINATION RULES (strict):\n"
            ."1. Pick one delivery owner per stage. Do not ask several specialists the same question.\n"
            ."2. Delegate a narrow, self-contained task. The specialist cannot see this conversation.\n"
            ."3. Use the sync ask tools when you need the answer to compose this reply; use delegate_to_bot only when the work can finish later.\n"
            ."4. You have no write tools. A specialist may propose a write, but it still requires human approval.\n"
            ."5. Never invent a specialist's result. If no tool returned it, say so.\n"
            ."6. Summarize the result yourself: outcome, facts, next step.\n"
            .'7. If the request spans workspaces or needs a platform action, escalate to the human administrator.';

        if (trim($this->userContext) !== '') {
            $instructions .= "\n\n{$this->userContext}";
        }

        $instructions .= "\n\n".PlatformKnowledge::adminNotes();

        return $instructions;
    }

    /**
     * @return Message[]
     */
    public function messages(): iterable
    {
        return $this->history;
    }

    /**
     * @return Tool[]
     */
    public function tools(): iterable
    {
        if (! AiBotsGate::enabled()) {
            return [];
        }

        $policy = $this->coordinator->policyObject()->toArray();
        $delegationMode = $policy['delegation']['mode'] ?? BotPolicy::DELEGATION_NONE;
        $allowedTargets = $policy['delegation']['allowed_targets'] ?? [];

        $roster = array_values(array_filter(
            $this->roster,
            fn (AiBot $bot): bool => $allowedTargets === [] || in_array($bot->slug, $allowedTargets, true),
        ));

        $tools = ToolRegistry::makeMany($this->coordinator->allowed_tools);

        if (in_array($delegationMode, [BotPolicy::DELEGATION_SYNC, BotPolicy::DELEGATION_SYNC_AND_ASYNC], true)) {
            foreach ($roster as $bot) {
                $tools[] = new AgentTool(new BotAgent(
                    $bot,
                    $this->actor,
                    $this->session,
                    [],
                    $this->userContext,
                    $this->depth + 1,
                ));
            }
        }

        if (in_array($delegationMode, [BotPolicy::DELEGATION_ASYNC, BotPolicy::DELEGATION_SYNC_AND_ASYNC], true)) {
            $tools[] = new DelegateToBot(
                $this->coordinator,
                $this->actor,
                $this->session,
                $this->depth,
                null,
                array_map(fn (AiBot $bot): string => $bot->slug, $roster),
            );
        }

        return $tools;
    }

    public function maxSteps(): int
    {
        return $this->coordinator->max_steps;
    }

    public function model(): ?string
    {
        return $this->coordinator->model;
    }

    public function provider(): ?string
    {
        return Setting::get('ai_provider', 'gemini');
    }
}
