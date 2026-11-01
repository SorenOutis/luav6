<?php

declare(strict_types=1);

namespace App\Ai\Support;

use App\Models\AiBot;

/**
 * Structured, admin-authored bot policy.
 *
 * The policy is the durable contract for a bot: what it owns, what it must
 * never do, how it delegates, and how it reports. It is stored as JSON and
 * compiled into the agent instructions so the prompt and the tool allowlist
 * cannot drift apart.
 *
 * Two invariants are locked here rather than trusted to the editor:
 * - writes always require a human approval (`PendingAiAction`), and
 * - data access is always workspace-scoped.
 */
final class BotPolicy
{
    public const WRITE_MODE_APPROVAL_REQUIRED = 'approval_required';

    public const DELEGATION_NONE = 'none';

    public const DELEGATION_SYNC = 'sync';

    public const DELEGATION_ASYNC = 'async';

    public const DELEGATION_SYNC_AND_ASYNC = 'sync_and_async';

    public const MAX_DELEGATION_DEPTH = 2;

    public const MAX_STEPS_HARD_CAP = 20;

    /** @var array<string, mixed> */
    private array $policy;

    /** @param array<string, mixed> $policy */
    private function __construct(array $policy)
    {
        $this->policy = $policy;
    }

    /**
     * Normalize raw editor input into the canonical policy shape.
     *
     * @param  array<string, mixed>|null  $policy
     * @return array<string, mixed>
     */
    public static function normalize(?array $policy, string $kind = AiBot::KIND_SPECIALIST): array
    {
        $policy ??= [];
        $isCoordinator = $kind === AiBot::KIND_COORDINATOR;

        $identity = is_array($policy['identity'] ?? null) ? $policy['identity'] : [];
        $scope = is_array($policy['scope'] ?? null) ? $policy['scope'] : [];
        $writes = is_array($policy['writes'] ?? null) ? $policy['writes'] : [];
        $dataAccess = is_array($policy['data_access'] ?? null) ? $policy['data_access'] : [];
        $delegation = is_array($policy['delegation'] ?? null) ? $policy['delegation'] : [];
        $escalation = is_array($policy['escalation'] ?? null) ? $policy['escalation'] : [];
        $output = is_array($policy['output'] ?? null) ? $policy['output'] : [];

        $delegationMode = (string) ($delegation['mode'] ?? ($isCoordinator
            ? self::DELEGATION_SYNC_AND_ASYNC
            : self::DELEGATION_NONE));

        if (! $isCoordinator) {
            $delegationMode = self::DELEGATION_NONE;
        } elseif (! in_array($delegationMode, [
            self::DELEGATION_SYNC,
            self::DELEGATION_ASYNC,
            self::DELEGATION_SYNC_AND_ASYNC,
        ], true)) {
            $delegationMode = self::DELEGATION_SYNC_AND_ASYNC;
        }

        $maxDepth = (int) ($delegation['max_depth'] ?? self::MAX_DELEGATION_DEPTH);
        $maxDepth = max(0, min(self::MAX_DELEGATION_DEPTH, $maxDepth));

        return [
            'version' => max(1, (int) ($policy['version'] ?? 1)),
            'identity' => [
                'role' => self::string($identity['role'] ?? ''),
                'mission' => self::string($identity['mission'] ?? ''),
                'tone' => self::string($identity['tone'] ?? 'concise, professional, plain language'),
                'model_tier' => in_array(($identity['model_tier'] ?? 'default'), ['default', 'fast', 'reasoning'], true)
                    ? (string) ($identity['model_tier'] ?? 'default')
                    : 'default',
            ],
            'scope' => [
                'owns' => self::stringList($scope['owns'] ?? []),
                'does_not_own' => self::stringList($scope['does_not_own'] ?? []),
            ],
            'writes' => [
                'mode' => self::WRITE_MODE_APPROVAL_REQUIRED,
                'notes' => self::string($writes['notes'] ?? 'Every write stages an expiring approval card.'),
            ],
            'data_access' => [
                'workspace_only' => true,
                'cross_workspace' => false,
                'student_pii' => in_array(($dataAccess['student_pii'] ?? 'minimize'), ['minimize', 'no_pii'], true)
                    ? (string) ($dataAccess['student_pii'] ?? 'minimize')
                    : 'minimize',
            ],
            'delegation' => [
                'mode' => $delegationMode,
                'allowed_targets' => $isCoordinator
                    ? self::stringList($delegation['allowed_targets'] ?? [])
                    : [],
                'max_depth' => $isCoordinator ? $maxDepth : 0,
            ],
            'escalation' => [
                'triggers' => self::stringList($escalation['triggers'] ?? []),
                'to' => $isCoordinator ? null : (self::string($escalation['to'] ?? 'chief') ?: 'chief'),
            ],
            'output' => [
                'format' => self::string($output['format'] ?? 'Outcome, facts, next step'),
                'must_include' => self::stringList($output['must_include'] ?? []),
                'must_not' => self::stringList($output['must_not'] ?? [
                    'Invented IDs, approval cards, or download links',
                    'Other workspaces or another user\'s private memory',
                ]),
            ],
            'guardrails' => self::stringList($policy['guardrails'] ?? [
                'Never claim an action was taken when it was only proposed.',
                'Resolve records with read tools before proposing a write.',
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $policy
     */
    public static function from(?array $policy, string $kind = AiBot::KIND_SPECIALIST): self
    {
        return new self(self::normalize($policy, $kind));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->policy;
    }

    /** @param array<string, mixed> $policy */
    public function merge(array $policy): self
    {
        return new self(self::normalize(array_replace_recursive($this->policy, $policy), $this->kind()));
    }

    public function kind(): string
    {
        $mode = $this->policy['delegation']['mode'] ?? self::DELEGATION_NONE;

        return $mode === self::DELEGATION_NONE ? AiBot::KIND_SPECIALIST : AiBot::KIND_COORDINATOR;
    }

    /**
     * Write action types derived from the bot's tool allowlist. The registry is
     * the only source of truth for which tool keys stage approvals.
     *
     * @param  array<int, string>  $allowedTools
     * @return list<string>
     */
    public function allowedActions(array $allowedTools): array
    {
        return array_values(array_intersect(ToolRegistry::writeKeys(), $allowedTools));
    }

    /**
     * Compile the structured policy into prompt text. This is trusted,
     * admin-authored configuration, so it is not wrapped as untrusted input.
     */
    public function compile(AiBot $bot): string
    {
        $policy = $this->policy;
        $lines = [
            'STRUCTURED ROLE POLICY (trusted admin configuration; follow it exactly)',
            'IDENTITY',
            '- Role: '.($policy['identity']['role'] ?: $bot->name),
            '- Mission: '.($policy['identity']['mission'] ?: $bot->title ?: 'Support the workspace team.'),
            '- Tone: '.$policy['identity']['tone'],
            '- Model tier: '.$policy['identity']['model_tier'],
            '',
            'SCOPE',
            '- You own: '.self::inlineList($policy['scope']['owns']),
            '- You do not own: '.self::inlineList($policy['scope']['does_not_own']),
            '',
            'WRITE POLICY',
            '- Mode: '.$policy['writes']['mode'].' — no write executes without a human approving the staged card.',
            '- '.$policy['writes']['notes'],
            '',
            'DATA ACCESS',
            '- Workspace only: yes. Never read or reference another workspace.',
            '- Cross-workspace access: forbidden.',
            '- Student PII handling: '.$policy['data_access']['student_pii'].'.',
            '',
            'DELEGATION',
            '- Mode: '.$policy['delegation']['mode'].'.',
            '- Allowed targets: '.self::inlineList($policy['delegation']['allowed_targets']),
            '- Maximum delegation depth: '.$policy['delegation']['max_depth'].'.',
            '',
            'ESCALATION',
            '- Escalate when: '.self::inlineList($policy['escalation']['triggers']),
            '- Escalate to: '.($policy['escalation']['to'] ?? 'the human administrator'),
            '',
            'OUTPUT CONTRACT',
            '- Format: '.$policy['output']['format'].'.',
            '- Must include: '.self::inlineList($policy['output']['must_include']),
            '- Must never include: '.self::inlineList($policy['output']['must_not']),
            '',
            'GUARDRAILS',
        ];

        foreach ($policy['guardrails'] as $guardrail) {
            $lines[] = '- '.$guardrail;
        }

        return implode("\n", $lines);
    }

    /**
     * Non-fatal configuration problems surfaced in the super-admin control
     * center. These never silently change the bot; they tell an operator what
     * to fix.
     *
     * @return list<string>
     */
    public function driftWarnings(AiBot $bot): array
    {
        $warnings = [];
        $policy = $this->policy;

        if (trim((string) $policy['identity']['role']) === '') {
            $warnings[] = 'Policy is missing an identity role.';
        }

        if (trim((string) $policy['identity']['mission']) === '') {
            $warnings[] = 'Policy is missing a mission.';
        }

        if ($bot->allowed_tools === []) {
            $warnings[] = 'No tools are allowlisted; the bot can only answer from its instructions.';
        }

        $unknown = ToolRegistry::unknownKeys($bot->allowed_tools);
        if ($unknown !== []) {
            $warnings[] = 'Unknown tool keys: '.implode(', ', $unknown).'.';
        }

        if ($bot->isCoordinator() && array_intersect(ToolRegistry::writeKeys(), $bot->allowed_tools) !== []) {
            $warnings[] = 'Coordinator bots must not hold write tools; they were removed at save time.';
        }

        if (! $bot->isCoordinator() && $policy['delegation']['mode'] !== self::DELEGATION_NONE) {
            $warnings[] = 'Specialist bots cannot delegate; delegation is coordinator-only.';
        }

        if ($bot->isCoordinator() && $policy['delegation']['mode'] === self::DELEGATION_NONE) {
            $warnings[] = 'Coordinator has no delegation mode configured.';
        }

        if ($policy['writes']['mode'] !== self::WRITE_MODE_APPROVAL_REQUIRED) {
            $warnings[] = 'Write mode must remain approval_required.';
        }

        if ($policy['data_access']['workspace_only'] !== true || $policy['data_access']['cross_workspace'] !== false) {
            $warnings[] = 'Data access must remain workspace-only.';
        }

        if ($bot->max_steps > self::MAX_STEPS_HARD_CAP) {
            $warnings[] = 'Max steps exceeds the hard cap of '.self::MAX_STEPS_HARD_CAP.'.';
        }

        return $warnings;
    }

    /** @param array<int, mixed> $values */
    private static function stringList(array $values): array
    {
        return array_values(array_filter(array_map(
            fn (mixed $value): string => self::string($value),
            $values,
        ), fn (string $value): bool => $value !== ''));
    }

    private static function string(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /** @param array<int, string> $values */
    private static function inlineList(array $values): string
    {
        return $values === [] ? 'none specified' : implode('; ', $values);
    }
}
