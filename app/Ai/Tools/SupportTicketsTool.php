<?php

namespace App\Ai\Tools;

use App\Models\SupportTicket;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SupportTicketsTool implements Tool
{
    public function name(): string
    {
        return 'support_tickets';
    }

    public function description(): Stringable|string
    {
        return 'List and search student support tickets for this workspace (active by default): subject, student, priority, status, and age. Pair with reply_support_ticket to answer them. Read-only.';
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

        $status = trim((string) ($request['status'] ?? 'active'));
        $allowed = ['active', 'all', ...array_keys(SupportTicket::STATUSES)];

        if (! in_array($status, $allowed, true)) {
            return 'Error: status must be one of: '.implode(', ', $allowed).'.';
        }

        $priority = trim((string) ($request['priority'] ?? ''));
        if ($priority !== '' && ! array_key_exists($priority, SupportTicket::PRIORITIES)) {
            return 'Error: priority must be one of: '.implode(', ', array_keys(SupportTicket::PRIORITIES)).'.';
        }

        $search = ! empty($request['search']) ? trim((string) $request['search']) : null;
        $limit = min(max((int) ($request['limit'] ?? 15), 1), 50);

        $base = SupportTicket::query()->where('workspace_id', $workspaceId);

        $counts = (clone $base)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total)
            ->all();

        $tickets = (clone $base)
            ->when($status === 'active', fn ($query) => $query->whereIn('status', [
                SupportTicket::STATUS_OPEN,
                SupportTicket::STATUS_IN_PROGRESS,
            ]))
            ->when(! in_array($status, ['active', 'all'], true), fn ($query) => $query->where('status', $status))
            ->when($priority !== '', fn ($query) => $query->where('priority', $priority))
            ->when($search, fn ($query) => $query->where(fn ($sub) => $sub
                ->where('subject', 'like', "%{$search}%")
                ->orWhere('message', 'like', "%{$search}%")))
            ->with('user:id,name,email')
            ->withCount('replies')
            ->withMax('replies', 'created_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (SupportTicket $ticket) => [
                'id' => $ticket->id,
                'subject' => (string) $ticket->subject,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'status_label' => SupportTicket::STATUSES[$ticket->status] ?? $ticket->status,
                'student' => $ticket->user ? [
                    'name' => (string) $ticket->user->name,
                    'email' => (string) $ticket->user->email,
                ] : null,
                'message_excerpt' => Str::limit((string) $ticket->message, 200),
                'created_at' => $ticket->created_at?->format('M d, Y g:i A'),
                'age_human' => $ticket->created_at?->diffForHumans(),
                'replies_count' => (int) $ticket->replies_count,
                'last_reply_at' => $ticket->replies_max_created_at
                    ? (string) $ticket->replies_max_created_at
                    : null,
            ])
            ->values();

        return json_encode([
            'counts_by_status' => $counts,
            'filter' => $status,
            'tickets' => $tickets,
            'note' => 'Use reply_support_ticket to draft a staged reply (and optionally resolve the ticket); the administrator still has to approve it.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->description('Filter: active (open + in progress, default), all, open, in_progress, resolved, or closed.'),
            'priority' => $schema->string()->description('Optional priority filter.'),
            'search' => $schema->string()->description('Optional subject/message search filter.'),
            'limit' => $schema->integer()->description('Maximum tickets to return (1-50, default 15).'),
        ];
    }
}
