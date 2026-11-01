<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class AiUsageLog extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'date',
        'provider',
        'model',
        'source',
        'bot_id',
        'input_tokens',
        'output_tokens',
        'neurons',
        'estimated_cost_micros',
        'workspace_id',
        'ai_budget_reservation_id',
    ];

    protected $casts = [
        'date' => 'date',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'neurons' => 'decimal:2',
        'estimated_cost_micros' => 'integer',
    ];

    public function budgetReservation()
    {
        return $this->belongsTo(AiBudgetReservation::class, 'ai_budget_reservation_id');
    }

    public function bot()
    {
        return $this->belongsTo(AiBot::class, 'bot_id');
    }
}
