<?php

namespace App\Ai\Tools;

use App\Models\PendingAiAction;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AiActionLogTool implements Tool
{
    public function name(): string
    {
        return 'ai_action_log';
    }

    public function description(): Stringable|string
    {
        return 'Review what the AI assistant has prepared and what happened to each request in this workspace: pending approvals, executed changes, rejections, failures, and expirations. Read-only audit view.';
    }

    public function handle(Request $request): Stringable|string
    {
        $admin = auth()->user();
        if (! $admin?->is_admin) {
            return 'Only admins can use this tool.';
        }

        $workspaceId = app(WorkspaceContext::class)->id();
        if (! $workspaceId) {
            return 'Error: no active workspace selected.';
        }

        $days = min(max((int) ($request['days'] ?? 7), 1), 90);
        $limit = min(max((int) ($request['limit'] ?? 20), 1), 50);
        $status = trim((string) ($request['status'] ?? ''));
        $actionType = trim((string) ($request['action_type'] ?? ''));

        $allowedStatuses = [
            PendingAiAction::STATUS_PENDING,
            PendingAiAction::STATUS_EXECUTING,
            PendingAiAction::STATUS_EXECUTED,
            PendingAiAction::STATUS_REJECTED,
            PendingAiAction::STATUS_FAILED,
            PendingAiAction::STATUS_EXPIRED,
        ];

        if ($status !== '' && ! in_array($status, $allowedStatuses, true)) {
            return 'Error: status must be one of: '.implode(', ', $allowedStatuses).'.';
        }

        $base = PendingAiAction::query()
            ->where('workspace_id', $workspaceId)
            ->where('created_at', '>=', now()->subDays($days))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($actionType !== '', fn ($query) => $query->where('action_type', $actionType));

        $counts = (clone $base)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total)
            ->all();

        $rows = (clone $base)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (PendingAiAction $action) => [
                'id' => $action->public_id,
                'action_type' => $action->action_type,
                'title' => (string) $action->title,
                'status' => $action->status,
                'requested_by' => $action->user?->name,
                'created_at' => $action->created_at?->format('M d, Y g:i A'),
                'resolved_at' => ($action->executed_at ?? $action->rejected_at ?? $action->failed_at)?->format('M d, Y g:i A'),
                'result' => $action->result ? Str::limit((string) $action->result, 200) : null,
                'error' => $action->error ? Str::limit((string) $action->error, 200) : null,
            ])
            ->values();

        return json_encode([
            'window_days' => $days,
            'total_in_window' => array_sum($counts),
            'counts_by_status' => $counts,
            'actions' => $rows,
            'note' => 'Every write requested through the assistant requires human approval; executed rows were approved by an administrator, pending rows are still awaiting review.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'days' => $schema->integer()->description('Lookback window in days (1-90, default 7).'),
            'status' => $schema->string()->description('Optional status filter: pending, executing, executed, rejected, failed, or expired.'),
            'action_type' => $schema->string()->description('Optional action type filter, e.g. "create_exam" or "record_grade".'),
            'limit' => $schema->integer()->description('Maximum number of actions to return (1-50, default 20).'),
        ];
    }
}
