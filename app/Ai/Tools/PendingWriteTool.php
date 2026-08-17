<?php

namespace App\Ai\Tools;

use App\Exceptions\PendingAiActionException;
use App\Services\PendingAiActionService;
use App\Support\WorkspaceContext;

abstract class PendingWriteTool
{
    public function __construct(
        protected ?PendingAiActionService $pendingActions = null,
        protected ?int $chatSessionId = null,
    ) {
        $this->pendingActions ??= app(PendingAiActionService::class);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, array{field: string, before: mixed, after: mixed}>  $changes
     */
    protected function stageAction(
        string $type,
        string $title,
        string $summary,
        array $payload,
        array $changes,
    ): string {
        try {
            $action = $this->pendingActions->stage(
                $type,
                $title,
                $summary,
                $payload,
                ['changes' => $changes],
                $this->chatSessionId,
            );
        } catch (PendingAiActionException $exception) {
            return 'Error preparing approval: '.$exception->getMessage();
        }

        $expiresAt = $action->expires_at?->format('g:i A');

        return 'PENDING HUMAN APPROVAL — no write was executed. '
            ."A review card for \"{$action->title}\" is now visible to the administrator"
            .($expiresAt ? " and expires at {$expiresAt}" : '')
            .'. The administrator must review the exact diff and click Approve; do not ask them to type a confirmation and do not call this tool again for the same change.';
    }

    protected function workspaceId(): ?int
    {
        return app(WorkspaceContext::class)->id();
    }

    protected function adminError(): ?string
    {
        return auth()->user()?->is_admin ? null : 'Only admins can use this tool.';
    }
}
