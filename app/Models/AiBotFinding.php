<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recurring anomaly a watcher bot observed in its domain.
 *
 * Fingerprints are semantic keys per condition, not data-derived, so one
 * condition produces a single updated row instead of a new row every run.
 */
class AiBotFinding extends Model
{
    use BelongsToWorkspace;

    public const SEVERITY_INFO = 'info';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITIES = [
        self::SEVERITY_INFO,
        self::SEVERITY_WARNING,
        self::SEVERITY_CRITICAL,
    ];

    public const STATUS_OPEN = 'open';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'workspace_id',
        'bot_id',
        'domain',
        'severity',
        'fingerprint',
        'title',
        'summary',
        'evidence',
        'suggested_action',
        'status',
        'times_observed',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'times_observed' => 'integer',
            'first_seen_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
            'notified_at' => 'immutable_datetime',
        ];
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(AiBot::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return Builder<AiBotFinding> */
    public function scopeActionable(Builder $query): Builder
    {
        return $query
            ->where('severity', '!=', self::SEVERITY_INFO)
            ->whereNotNull('suggested_action');
    }

    /** @return Builder<AiBotFinding> */
    public function scopeOpenStatuses(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }
}
