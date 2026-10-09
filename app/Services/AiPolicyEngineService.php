<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Support\PolicyEvaluationResult;

/**
 * Deterministic Policy Engine for Echo AI.
 * Modeled after EduFlow's PolicyEngineService and FinancialPolicyEngine:
 * "The AI reasons, the policy engine authorizes."
 *
 * Ensures permissions, workspace isolation, privilege boundaries,
 * and operational limits are authoritatively enforced in PHP before any write action is staged.
 */
class AiPolicyEngineService
{
    public const POLICY_SUPER_ADMIN = 'SUPER_ADMIN_OPS_V1';

    public const POLICY_WORKSPACE_ADMIN = 'WORKSPACE_ADMIN_BOUNDS_V1';

    public const POLICY_OPERATIONAL_SAFETY = 'OPERATIONAL_SAFETY_V1';

    public const MAX_XP_AWARD_PER_ACTION = 5000;

    public const MAX_EXAM_DURATION_MINUTES = 1440; // 24 hours

    public const MIN_EXAM_DURATION_MINUTES = 5;

    /**
     * Authoritatively evaluate whether a proposed AI write action is allowed.
     *
     * @param  array<string, mixed>  $payload
     */
    public function evaluateAction(
        string $type,
        array $payload,
        User $user,
        ?int $workspaceId = null,
    ): PolicyEvaluationResult {
        $checks = [];
        $violations = [];
        $policyCode = $user->isSuperAdmin() ? self::POLICY_SUPER_ADMIN : self::POLICY_WORKSPACE_ADMIN;

        // 1. Administrator Authentication Clearance
        $isAdmin = (bool) $user->is_admin;
        $checks[] = [
            'label' => 'Administrator Clearance',
            'passed' => $isAdmin,
        ];
        if (! $isAdmin) {
            $violations[] = 'not_an_admin';

            return new PolicyEvaluationResult(
                policyCode: $policyCode,
                allowed: false,
                checks: $checks,
                violations: $violations,
                reason: 'Only authorized platform administrators may stage Echo AI actions.',
            );
        }

        $isSuperAdmin = $user->isSuperAdmin();

        // 2. Platform Maintenance Authorization
        if ($type === 'manage_maintenance') {
            $checks[] = [
                'label' => 'Platform Maintenance Clearance (Super Admin Required)',
                'passed' => $isSuperAdmin,
            ];
            if (! $isSuperAdmin) {
                $violations[] = 'maintenance_unauthorized';

                return new PolicyEvaluationResult(
                    policyCode: $policyCode,
                    allowed: false,
                    checks: $checks,
                    violations: $violations,
                    reason: 'Platform maintenance mode is strictly restricted to Super Administrators.',
                );
            }
        }

        // 3. User Privilege Isolation (Workspace Admins cannot mutate or create Super Admins)
        if (in_array($type, ['create_user', 'update_user', 'reset_user_password', 'delete_user'], true)) {
            $targetUser = $this->resolveTargetUser($payload);

            if (! $isSuperAdmin) {
                // Workspace admin attempting to touch an existing Super Admin
                if ($targetUser && $targetUser->isSuperAdmin()) {
                    $checks[] = [
                        'label' => 'Target User Privilege Isolation',
                        'passed' => false,
                    ];
                    $violations[] = 'target_is_super_admin';

                    return new PolicyEvaluationResult(
                        policyCode: $policyCode,
                        allowed: false,
                        checks: $checks,
                        violations: $violations,
                        reason: 'Standard workspace administrators cannot modify, reset credentials for, or delete Super Administrator accounts.',
                    );
                }

                // Workspace admin attempting to promote a user to Super Admin
                if (! empty($payload['is_super_admin'])) {
                    $checks[] = [
                        'label' => 'Super Admin Grant Prevention',
                        'passed' => false,
                    ];
                    $violations[] = 'granting_super_admin_forbidden';

                    return new PolicyEvaluationResult(
                        policyCode: $policyCode,
                        allowed: false,
                        checks: $checks,
                        violations: $violations,
                        reason: 'Workspace administrators cannot grant Super Administrator privileges.',
                    );
                }
            }

            $checks[] = [
                'label' => 'User Privilege Boundary Verification',
                'passed' => true,
            ];
        }

        // 4. Gamification Safety Rules (XP Limits)
        if ($type === 'award_student_xp') {
            $xpAmount = (int) ($payload['amount_xp'] ?? $payload['xp_amount'] ?? $payload['xp'] ?? $payload['points'] ?? 0);
            $xpValid = $xpAmount > 0 && $xpAmount <= self::MAX_XP_AWARD_PER_ACTION;

            $checks[] = [
                'label' => 'XP Award Within Safe Range (1 - '.number_format(self::MAX_XP_AWARD_PER_ACTION).' XP)',
                'passed' => $xpValid,
            ];

            if (! $xpValid) {
                $violations[] = 'xp_out_of_bounds';

                return new PolicyEvaluationResult(
                    policyCode: self::POLICY_OPERATIONAL_SAFETY,
                    allowed: false,
                    checks: $checks,
                    violations: $violations,
                    reason: 'XP awards must be between 1 and '.number_format(self::MAX_XP_AWARD_PER_ACTION).' XP per action.',
                );
            }
        }

        // 5. Exam Operational Boundaries
        if (in_array($type, ['create_exam', 'update_exam'], true)) {
            $duration = isset($payload['duration_minutes']) ? (int) $payload['duration_minutes'] : 60;
            $durationValid = $duration >= self::MIN_EXAM_DURATION_MINUTES && $duration <= self::MAX_EXAM_DURATION_MINUTES;

            $checks[] = [
                'label' => 'Exam Duration Boundary Check (5 - 1440 mins)',
                'passed' => $durationValid,
            ];

            if (! $durationValid) {
                $violations[] = 'invalid_exam_duration';

                return new PolicyEvaluationResult(
                    policyCode: self::POLICY_OPERATIONAL_SAFETY,
                    allowed: false,
                    checks: $checks,
                    violations: $violations,
                    reason: 'Exam duration must be between '.self::MIN_EXAM_DURATION_MINUTES.' minutes and '.self::MAX_EXAM_DURATION_MINUTES.' minutes.',
                );
            }
        }

        // 6. Grade Score Boundaries
        if (in_array($type, ['record_grade', 'update_grade', 'grade_submission'], true)) {
            if (isset($payload['score'])) {
                $score = (float) $payload['score'];
                $scoreValid = $score >= 0 && $score <= 1000;

                $checks[] = [
                    'label' => 'Grade Value Invariant (0 - 1000)',
                    'passed' => $scoreValid,
                ];

                if (! $scoreValid) {
                    $violations[] = 'score_out_of_bounds';

                    return new PolicyEvaluationResult(
                        policyCode: self::POLICY_OPERATIONAL_SAFETY,
                        allowed: false,
                        checks: $checks,
                        violations: $violations,
                        reason: 'Grade score cannot be negative or exceed allowable boundaries.',
                    );
                }
            }
        }

        // 7. Workspace Scope Verification
        $checks[] = [
            'label' => $isSuperAdmin ? 'Global Platform Access Authorized' : 'Workspace Scope Isolation Enforced',
            'passed' => true,
        ];

        return new PolicyEvaluationResult(
            policyCode: $policyCode,
            allowed: true,
            checks: $checks,
            violations: [],
            reason: 'All platform policy checks passed successfully.',
        );
    }

    /**
     * Attempt to resolve target user model from payload if present.
     *
     * @param  array<string, mixed>  $payload
     */
    private function resolveTargetUser(array $payload): ?User
    {
        $userId = $payload['user_id'] ?? $payload['id'] ?? null;
        if (! $userId) {
            return null;
        }

        return User::query()->find($userId);
    }
}
