export type AiActionStatus =
    | 'pending'
    | 'executing'
    | 'executed'
    | 'rejected'
    | 'failed'
    | 'expired';

export interface AiActionChange {
    field: string;
    before: unknown;
    after: unknown;
}

export interface PendingAiAction {
    id: string;
    actionType: string;
    title: string;
    summary: string;
    status: AiActionStatus;
    workspace: { id: string; name: string } | null;
    changes: AiActionChange[];
    expiresAt: string | null;
    approvedAt: string | null;
    executedAt: string | null;
    result: string | null;
    error: string | null;
    nonce: string | null;
}
