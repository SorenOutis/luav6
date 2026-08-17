<?php

namespace App\Services;

use App\Exceptions\PendingAiActionException;
use App\Models\ChatSession;
use App\Models\PendingAiAction;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class PendingAiActionService
{
    public const EXPIRATION_MINUTES = 20;

    private const EXECUTION_RETRY_MINUTES = 10;

    private const MAX_VISIBLE_ACTIONS = 20;

    private const ALLOWED_TYPES = [
        'create_exam',
        'update_exam',
        'post_announcement',
        'create_assignment',
        'generate_exam_questions',
    ];

    public function __construct(
        private readonly AiActionExecutor $executor,
        private readonly WorkspaceContext $workspaceContext,
    ) {}

    /**
     * Stage an immutable action for review. The nonce is generated and retained
     * by the server; it is never returned through the model tool response.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $preview
     */
    public function stage(
        string $type,
        string $title,
        string $summary,
        array $payload,
        array $preview,
        ?int $chatSessionId = null,
    ): PendingAiAction {
        $user = auth()->user();
        if (! $user?->is_admin) {
            throw new PendingAiActionException('Only administrators can prepare AI write actions.', 403);
        }
        if (! in_array($type, self::ALLOWED_TYPES, true)) {
            throw new PendingAiActionException('This AI action type is not supported.');
        }

        $workspaceId = $this->workspaceContext->id();
        if (! $workspaceId) {
            throw new PendingAiActionException('Select an active workspace before preparing a write action.');
        }
        $this->assertWorkspaceAccess($user, $workspaceId);

        if ($chatSessionId && ! ChatSession::query()
            ->whereKey($chatSessionId)
            ->where('user_id', $user->id)
            ->exists()) {
            throw new PendingAiActionException('The chat session for this action could not be verified.', 404);
        }

        $payloadHash = $this->payloadHash($payload, $preview);
        $existing = PendingAiAction::query()
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $user->id)
            ->when($chatSessionId, fn ($query) => $query->where('chat_session_id', $chatSessionId))
            ->when(! $chatSessionId, fn ($query) => $query->whereNull('chat_session_id'))
            ->where('action_type', $type)
            ->where('payload_hash', $payloadHash)
            ->where('status', PendingAiAction::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($existing) {
            $this->audit($existing, 'duplicate_request_deduplicated', [
                'source' => 'ai_tool',
            ]);

            return $existing;
        }

        $nonce = Str::random(64);

        return DB::transaction(function () use ($workspaceId, $user, $chatSessionId, $type, $title, $summary, $payload, $payloadHash, $preview, $nonce): PendingAiAction {
            $action = PendingAiAction::query()->create([
                'workspace_id' => $workspaceId,
                'user_id' => $user->id,
                'chat_session_id' => $chatSessionId,
                'action_type' => $type,
                'title' => $title,
                'summary' => $summary,
                'payload' => $payload,
                'payload_hash' => $payloadHash,
                'preview' => $preview,
                'nonce_ciphertext' => Crypt::encryptString($nonce),
                'status' => PendingAiAction::STATUS_PENDING,
                'expires_at' => now()->addMinutes(self::EXPIRATION_MINUTES),
            ]);

            $this->audit($action, 'created', [
                'source' => 'ai_tool',
                'payload_hash' => $payloadHash,
                'expires_at' => $action->expires_at?->toIso8601String(),
            ]);

            return $action;
        });
    }

    /** @return Collection<int, PendingAiAction> */
    public function forUser(User $user, ?int $chatSessionId = null): Collection
    {
        if (! $user->is_admin) {
            throw new PendingAiActionException('AI actions are available only to administrators.', 403);
        }

        $this->recoverStaleExecutions($user);
        $this->expirePending($user);

        if ($chatSessionId && ! ChatSession::query()
            ->whereKey($chatSessionId)
            ->where('user_id', $user->id)
            ->exists()) {
            throw new PendingAiActionException('Chat session not found.', 404);
        }

        return PendingAiAction::query()
            ->with('workspace:id,public_id,name')
            ->where('user_id', $user->id)
            ->when($chatSessionId, fn ($query) => $query->where('chat_session_id', $chatSessionId))
            ->where('created_at', '>=', now()->subDays(2))
            ->latest('id')
            ->limit(self::MAX_VISIBLE_ACTIONS)
            ->get();
    }

    /**
     * Execute exactly once after a human click supplies the server-issued
     * nonce. A replay of a completed approval returns the original result.
     *
     * @param  array<string, mixed>  $auditMetadata
     */
    public function approve(PendingAiAction $action, User $user, string $nonce, array $auditMetadata = []): PendingAiAction
    {
        $this->assertActor($action, $user);
        $this->assertWorkspaceAccess($user, (int) $action->workspace_id);
        $this->assertIntegrity($action, $nonce);

        $claim = DB::transaction(function () use ($action, $user, $nonce, $auditMetadata): array {
            $locked = PendingAiAction::query()->lockForUpdate()->findOrFail($action->id);
            $this->assertActor($locked, $user);
            $this->assertIntegrity($locked, $nonce);

            if ($locked->status === PendingAiAction::STATUS_EXECUTED) {
                return ['state' => PendingAiAction::STATUS_EXECUTED, 'token' => null];
            }
            if ($locked->status === PendingAiAction::STATUS_EXECUTING) {
                if ($locked->execution_started_at?->lte(now()->subMinutes(self::EXECUTION_RETRY_MINUTES))) {
                    $executionToken = (string) Str::uuid7();
                    $locked->forceFill([
                        'execution_token' => $executionToken,
                        'execution_started_at' => now(),
                    ])->save();
                    $this->audit($locked, 'execution_retried', $auditMetadata);

                    return ['state' => 'claimed', 'token' => $executionToken];
                }

                return ['state' => PendingAiAction::STATUS_EXECUTING, 'token' => null];
            }
            if ($locked->status !== PendingAiAction::STATUS_PENDING) {
                throw new PendingAiActionException("This action is already {$locked->status}.", 409);
            }
            if ($locked->expires_at->isPast()) {
                $locked->forceFill(['status' => PendingAiAction::STATUS_EXPIRED])->save();
                $this->audit($locked, 'expired', $auditMetadata);

                return ['state' => PendingAiAction::STATUS_EXPIRED, 'token' => null];
            }

            $executionToken = (string) Str::uuid7();
            $locked->forceFill([
                'status' => PendingAiAction::STATUS_EXECUTING,
                'approved_at' => now(),
                'execution_token' => $executionToken,
                'execution_started_at' => now(),
                'error' => null,
            ])->save();
            $this->audit($locked, 'approved', $auditMetadata);

            return ['state' => 'claimed', 'token' => $executionToken];
        });

        if ($claim['state'] === PendingAiAction::STATUS_EXPIRED) {
            throw new PendingAiActionException('This approval request expired. Ask Echo to prepare it again.', 410);
        }

        if ($claim['state'] !== 'claimed') {
            return PendingAiAction::query()->with('workspace:id,public_id,name')->findOrFail($action->id);
        }

        $executionToken = $claim['token'];

        try {
            $apply = $this->workspaceContext->run(
                (int) $action->workspace_id,
                fn () => $this->executor->prepare($action->fresh()),
            );

            $this->workspaceContext->run((int) $action->workspace_id, function () use ($action, $apply, $auditMetadata, $executionToken): void {
                DB::transaction(function () use ($action, $apply, $auditMetadata, $executionToken): void {
                    $locked = PendingAiAction::query()->lockForUpdate()->findOrFail($action->id);

                    if ($locked->status === PendingAiAction::STATUS_EXECUTED) {
                        return;
                    }
                    if (
                        $locked->status !== PendingAiAction::STATUS_EXECUTING
                        || ! hash_equals((string) $locked->execution_token, (string) $executionToken)
                    ) {
                        throw new PendingAiActionException('A newer execution attempt replaced this one.', 409);
                    }

                    $result = $apply();
                    $locked->forceFill([
                        'status' => PendingAiAction::STATUS_EXECUTED,
                        'result' => Str::limit($result, 10000, ''),
                        'executed_at' => now(),
                        'error' => null,
                    ])->save();
                    $this->audit($locked, 'executed', array_merge($auditMetadata, [
                        'result_sha256' => hash('sha256', $result),
                    ]));
                });
            });
        } catch (Throwable $exception) {
            $this->markFailed($action, $executionToken, $exception, $auditMetadata);

            if ($exception instanceof PendingAiActionException) {
                throw $exception;
            }

            throw new PendingAiActionException('The action could not be completed: '.$exception->getMessage());
        }

        return PendingAiAction::query()->with('workspace:id,public_id,name')->findOrFail($action->id);
    }

    /** @param array<string, mixed> $auditMetadata */
    public function reject(PendingAiAction $action, User $user, string $nonce, array $auditMetadata = []): PendingAiAction
    {
        $this->assertActor($action, $user);
        $this->assertWorkspaceAccess($user, (int) $action->workspace_id);
        $this->assertIntegrity($action, $nonce);

        $expired = DB::transaction(function () use ($action, $user, $nonce, $auditMetadata): bool {
            $locked = PendingAiAction::query()->lockForUpdate()->findOrFail($action->id);
            $this->assertActor($locked, $user);
            $this->assertIntegrity($locked, $nonce);

            if ($locked->status === PendingAiAction::STATUS_REJECTED) {
                return false;
            }
            if ($locked->status !== PendingAiAction::STATUS_PENDING) {
                throw new PendingAiActionException("This action is already {$locked->status}.", 409);
            }
            if ($locked->expires_at->isPast()) {
                $locked->forceFill(['status' => PendingAiAction::STATUS_EXPIRED])->save();
                $this->audit($locked, 'expired', $auditMetadata);

                return true;
            }

            $locked->forceFill([
                'status' => PendingAiAction::STATUS_REJECTED,
                'rejected_at' => now(),
            ])->save();
            $this->audit($locked, 'rejected', $auditMetadata);

            return false;
        });

        if ($expired) {
            throw new PendingAiActionException('This approval request expired. Ask Echo to prepare it again.', 410);
        }

        return PendingAiAction::query()->with('workspace:id,public_id,name')->findOrFail($action->id);
    }

    /** @return array<string, mixed> */
    public function present(PendingAiAction $action): array
    {
        $user = auth()->user();
        if (! $user || ! $user->is_admin || (int) $action->user_id !== (int) $user->id) {
            throw new PendingAiActionException('AI action not found.', 404);
        }

        $action->loadMissing('workspace:id,public_id,name');

        return [
            'id' => $action->public_id,
            'actionType' => $action->action_type,
            'title' => $action->title,
            'summary' => $action->summary,
            'status' => $action->status,
            'workspace' => $action->workspace ? [
                'id' => $action->workspace->public_id,
                'name' => $action->workspace->name,
            ] : null,
            'changes' => $action->preview['changes'] ?? [],
            'expiresAt' => $action->expires_at?->toIso8601String(),
            'approvedAt' => $action->approved_at?->toIso8601String(),
            'executedAt' => $action->executed_at?->toIso8601String(),
            'result' => $action->result,
            'error' => $action->error,
            // Only the authenticated owner receives this DTO. The model tool
            // response never includes the nonce.
            'nonce' => $action->status === PendingAiAction::STATUS_PENDING
                ? $this->nonce($action)
                : null,
        ];
    }

    /** @param array<string, mixed> $metadata */
    private function audit(PendingAiAction $action, string $event, array $metadata = []): void
    {
        $action->audits()->create([
            'workspace_id' => $action->workspace_id,
            'actor_id' => auth()->id(),
            'event' => $event,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }

    private function assertActor(PendingAiAction $action, User $user): void
    {
        if (! $user->is_admin || (int) $action->user_id !== (int) $user->id) {
            throw new PendingAiActionException('AI action not found.', 404);
        }
    }

    private function assertWorkspaceAccess(User $user, int $workspaceId): void
    {
        $workspaceActive = Workspace::query()
            ->whereKey($workspaceId)
            ->whereNull('archived_at')
            ->exists();
        $isWorkspaceAdmin = $user->workspaces()
            ->whereKey($workspaceId)
            ->wherePivotIn('role', [Workspace::ROLE_OWNER, Workspace::ROLE_ADMIN])
            ->exists();

        if (! $workspaceActive || (! $user->isSuperAdmin() && ! $isWorkspaceAdmin)) {
            throw new PendingAiActionException('You no longer have administrator access to this action\'s workspace.', 403);
        }
    }

    private function assertIntegrity(PendingAiAction $action, string $nonce): void
    {
        if (! hash_equals($this->nonce($action), $nonce)) {
            $this->audit($action, 'invalid_nonce', [
                'nonce_sha256' => hash('sha256', $nonce),
            ]);
            throw new PendingAiActionException('The approval token is invalid. Refresh the action and try again.', 403);
        }
        if (! hash_equals($action->payload_hash, $this->payloadHash($action->payload, $action->preview))) {
            $this->audit($action, 'payload_integrity_failed');
            throw new PendingAiActionException('The action payload failed its integrity check.', 409);
        }
    }

    private function nonce(PendingAiAction $action): string
    {
        try {
            return Crypt::decryptString($action->nonce_ciphertext);
        } catch (Throwable) {
            throw new PendingAiActionException('The approval token can no longer be verified. Ask Echo to prepare the action again.', 409);
        }
    }

    private function payloadHash(array $payload, array $preview): string
    {
        $canonicalPayload = json_encode(
            $this->canonicalize([
                'payload' => $payload,
                'preview' => $preview,
            ]),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
        );

        return hash_hmac('sha256', $canonicalPayload, (string) config('app.key'));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        ksort($value);

        return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
    }

    /** @param array<string, mixed> $auditMetadata */
    private function markFailed(PendingAiAction $action, ?string $executionToken, Throwable $exception, array $auditMetadata): void
    {
        DB::transaction(function () use ($action, $executionToken, $exception, $auditMetadata): void {
            $locked = PendingAiAction::query()->lockForUpdate()->find($action->id);
            if (
                ! $locked
                || $locked->status !== PendingAiAction::STATUS_EXECUTING
                || ! hash_equals((string) $locked->execution_token, (string) $executionToken)
            ) {
                return;
            }

            $message = Str::limit($exception->getMessage() ?: 'Execution failed.', 2000, '');
            $locked->forceFill([
                'status' => PendingAiAction::STATUS_FAILED,
                'failed_at' => now(),
                'error' => $message,
            ])->save();
            $this->audit($locked, 'failed', array_merge($auditMetadata, [
                'exception' => $exception::class,
                'message' => $message,
            ]));
        });
    }

    private function recoverStaleExecutions(User $user): void
    {
        PendingAiAction::query()
            ->where('user_id', $user->id)
            ->where('status', PendingAiAction::STATUS_EXECUTING)
            ->where('execution_started_at', '<=', now()->subMinutes(self::EXECUTION_RETRY_MINUTES))
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->each(function (PendingAiAction $action): void {
                DB::transaction(function () use ($action): void {
                    $locked = PendingAiAction::query()->lockForUpdate()->find($action->id);
                    if (
                        ! $locked
                        || $locked->status !== PendingAiAction::STATUS_EXECUTING
                        || $locked->execution_started_at?->gt(now()->subMinutes(self::EXECUTION_RETRY_MINUTES))
                    ) {
                        return;
                    }

                    $locked->forceFill([
                        'status' => PendingAiAction::STATUS_PENDING,
                        'approved_at' => null,
                        'execution_token' => null,
                        'execution_started_at' => null,
                    ])->save();
                    $this->audit($locked, 'stale_execution_recovered', [
                        'source' => 'listing',
                    ]);
                });
            });
    }

    private function expirePending(User $user): void
    {
        PendingAiAction::query()
            ->where('user_id', $user->id)
            ->where('status', PendingAiAction::STATUS_PENDING)
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->each(function (PendingAiAction $action): void {
                $action->forceFill(['status' => PendingAiAction::STATUS_EXPIRED])->save();
                $this->audit($action, 'expired', ['source' => 'listing']);
            });
    }
}
