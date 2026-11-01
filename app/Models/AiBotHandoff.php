<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\AiBotHandoffFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiBotHandoff extends Model
{
    /** @use HasFactory<AiBotHandoffFactory> */
    use BelongsToWorkspace;

    use HasFactory;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'workspace_id',
        'from_bot_id',
        'requested_by_user_id',
        'to_bot_id',
        'chat_session_id',
        'parent_handoff_id',
        'task',
        'result',
        'status',
        'error',
        'depth',
        'estimated_cost_micros',
    ];

    protected function casts(): array
    {
        return [
            'depth' => 'integer',
            'estimated_cost_micros' => 'integer',
        ];
    }

    /** @return Builder<AiBotHandoff> */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_QUEUED, self::STATUS_RUNNING]);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function fromBot(): BelongsTo
    {
        return $this->belongsTo(AiBot::class, 'from_bot_id');
    }

    public function toBot(): BelongsTo
    {
        return $this->belongsTo(AiBot::class, 'to_bot_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function chatSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_handoff_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_handoff_id');
    }
}
