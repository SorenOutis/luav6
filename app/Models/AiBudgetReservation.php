<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AiBudgetReservation extends Model
{
    use BelongsToWorkspace;

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_RELEASED = 'released';

    protected $fillable = [
        'workspace_id',
        'user_id',
        'feature',
        'provider',
        'model',
        'reserved_input_tokens',
        'reserved_output_tokens',
        'reserved_cost_micros',
        'actual_input_tokens',
        'actual_output_tokens',
        'actual_cost_micros',
        'status',
        'expires_at',
        'settled_at',
        'released_at',
        'failure_reason',
    ];

    protected static function booted(): void
    {
        static::creating(function (AiBudgetReservation $reservation): void {
            $reservation->public_id ??= (string) Str::uuid7();
        });
    }

    protected function casts(): array
    {
        return [
            'reserved_input_tokens' => 'integer',
            'reserved_output_tokens' => 'integer',
            'reserved_cost_micros' => 'integer',
            'actual_input_tokens' => 'integer',
            'actual_output_tokens' => 'integer',
            'actual_cost_micros' => 'integer',
            'expires_at' => 'immutable_datetime',
            'settled_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
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
}
