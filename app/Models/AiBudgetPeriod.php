<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiBudgetPeriod extends Model
{
    use BelongsToWorkspace;

    public const TYPE_DAILY = 'daily';

    public const TYPE_MONTHLY = 'monthly';

    protected $fillable = [
        'workspace_id',
        'period_type',
        'period_start',
        'used_input_tokens',
        'used_output_tokens',
        'reserved_tokens',
        'used_cost_micros',
        'reserved_cost_micros',
        'request_count',
        'blocked_count',
        'warning_emitted_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'immutable_date',
            'used_input_tokens' => 'integer',
            'used_output_tokens' => 'integer',
            'reserved_tokens' => 'integer',
            'used_cost_micros' => 'integer',
            'reserved_cost_micros' => 'integer',
            'request_count' => 'integer',
            'blocked_count' => 'integer',
            'warning_emitted_at' => 'immutable_datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
