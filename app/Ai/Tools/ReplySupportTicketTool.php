<?php

namespace App\Ai\Tools;

use App\Models\SupportTicket;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ReplySupportTicketTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'reply_support_ticket';
    }

    public function description(): Stringable|string
    {
        return 'Prepare a reply to a student support ticket, optionally changing its status (in progress, resolved, closed, or reopened). The student is notified when the reply is approved and posted. This tool never replies directly; only the administrator approving in the UI can execute it.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        $workspaceId = $this->workspaceId();
        if (! $workspaceId) {
            return 'Error: no active workspace selected.';
        }

        $ticketId = (int) ($request['ticket_id'] ?? 0);
        $ticket = SupportTicket::query()
            ->where('workspace_id', $workspaceId)
            ->whereKey($ticketId)
            ->with('user:id,name')
            ->first();

        if (! $ticket) {
            return "Error: support ticket #{$ticketId} not found in this workspace. Use the support_tickets tool to find valid ticket IDs.";
        }

        $message = trim(strip_tags((string) ($request['message'] ?? '')));
        if ($message === '') {
            return 'Error: reply message is required.';
        }
        if (mb_strlen($message) > 2000) {
            return 'Error: reply message is too long (maximum 2000 characters).';
        }

        $newStatus = trim((string) ($request['status'] ?? ''));
        $allowedStatuses = [
            SupportTicket::STATUS_OPEN,
            SupportTicket::STATUS_IN_PROGRESS,
            SupportTicket::STATUS_RESOLVED,
            SupportTicket::STATUS_CLOSED,
        ];

        if ($newStatus !== '' && ! in_array($newStatus, $allowedStatuses, true)) {
            return 'Error: status must be one of: '.implode(', ', $allowedStatuses).'.';
        }

        if ($newStatus === $ticket->status) {
            $newStatus = '';
        }

        $preview = [
            ['field' => 'Ticket', 'before' => null, 'after' => "#{$ticket->id} {$ticket->subject}"],
            ['field' => 'Student', 'before' => null, 'after' => $ticket->user?->name ?? 'Unknown'],
            ['field' => 'Reply', 'before' => null, 'after' => $message],
        ];

        if ($newStatus !== '') {
            $preview[] = [
                'field' => 'Status',
                'before' => SupportTicket::STATUSES[$ticket->status] ?? $ticket->status,
                'after' => SupportTicket::STATUSES[$newStatus] ?? $newStatus,
            ];
        }

        $statusSummary = $newStatus !== ''
            ? ' and set status to '.(SupportTicket::STATUSES[$newStatus] ?? $newStatus)
            : '';

        return $this->stageAction(
            'reply_support_ticket',
            'Reply to support ticket',
            "Reply to ticket \"{$ticket->subject}\" (#{$ticket->id}){$statusSummary}. The student will be notified when approved.",
            [
                'ticket_id' => $ticket->id,
                'message' => $message,
                'new_status' => $newStatus !== '' ? $newStatus : null,
                'expected_updated_at' => $ticket->updated_at?->toJSON(),
            ],
            $preview,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'ticket_id' => $schema->integer()->description('The ID of the support ticket from the support_tickets tool.')->required(),
            'message' => $schema->string()->description('The reply text shown to the student (maximum 2000 characters).')->required(),
            'status' => $schema->string()->description('Optional new status: in_progress, resolved, closed, or open (to reopen).'),
        ];
    }
}
