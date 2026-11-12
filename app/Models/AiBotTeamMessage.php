<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message in the internal Bot Team Room.
 *
 * Watcher bots post their reasoning here ("I found X, I suggest Y") and the
 * Chief of Staffs specialists' all-clear notes keep the thread alive. The room
 * is super-admin-only and bot-authored; user message contents never appear.
 */
class AiBotTeamMessage extends Model
{
    use BelongsToWorkspace;

    public const KIND_OBSERVATION = 'observation';

    public const KIND_SUGGESTION = 'suggestion';

    public const KIND_ALL_CLEAR = 'all_clear';

    protected $fillable = [
        'workspace_id',
        'bot_id',
        'kind',
        'severity',
        'body',
        'finding_id',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(AiBot::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(AiBotFinding::class);
    }

    /** @return Builder<AiBotTeamMessage> */
    public function scopeAllClearToday(Builder $query): Builder
    {
        return $query
            ->where('kind', self::KIND_ALL_CLEAR)
            ->where('created_at', '>=', now()->startOfDay());
    }
}
