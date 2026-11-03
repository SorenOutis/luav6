<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\AiBotRoutineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiBotRoutine extends Model
{
    /** @use HasFactory<AiBotRoutineFactory> */
    use BelongsToWorkspace;

    use HasFactory;

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'workspace_id',
        'bot_id',
        'name',
        'cron',
        'prompt',
        'is_active',
        'last_run_at',
        'last_status',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_run_at' => 'immutable_datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(AiBot::class, 'bot_id');
    }
}
