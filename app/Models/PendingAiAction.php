<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PendingAiAction extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_EXECUTING = 'executing';

    public const STATUS_EXECUTED = 'executed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'workspace_id',
        'user_id',
        'chat_session_id',
        'action_type',
        'title',
        'summary',
        'payload',
        'payload_hash',
        'preview',
        'nonce_ciphertext',
        'status',
        'expires_at',
        'approved_at',
        'execution_token',
        'execution_started_at',
        'executed_at',
        'rejected_at',
        'failed_at',
        'result',
        'error',
    ];

    protected static function booted(): void
    {
        static::creating(function (PendingAiAction $action): void {
            $action->public_id ??= (string) Str::uuid7();
        });
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'preview' => 'array',
            'expires_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'execution_started_at' => 'immutable_datetime',
            'executed_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chatSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(AiActionAudit::class);
    }
}
