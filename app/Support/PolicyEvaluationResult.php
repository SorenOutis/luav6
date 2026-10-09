<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Deterministic policy evaluation result.
 * Modeled after EduFlow's PolicyEvaluationResult pattern:
 * the AI reasons, the policy engine authorizes.
 */
final readonly class PolicyEvaluationResult
{
    /**
     * @param  list<array{label: string, passed: bool}>  $checks
     * @param  list<string>  $violations
     */
    public function __construct(
        public string $policyCode,
        public bool $allowed,
        public array $checks = [],
        public array $violations = [],
        public ?string $reason = null,
    ) {}

    public function passed(): bool
    {
        return $this->allowed && empty($this->violations);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'policy_code' => $this->policyCode,
            'allowed' => $this->allowed,
            'checks' => $this->checks,
            'violations' => $this->violations,
            'reason' => $this->reason,
        ];
    }
}
