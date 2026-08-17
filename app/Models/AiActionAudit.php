<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiActionAudit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'pending_ai_action_id',
        'workspace_id',
        'actor_id',
        'event',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(PendingAiAction::class, 'pending_ai_action_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
