<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One group invitation for a group activity.
 *
 * Lifecycle: pending → accepted | declined (invitee) | cancelled (creator)
 * | expired (due date passed without a response). Terminal states stay in the
 * table as history; a re-invite creates a new row. At most one live invite
 * per student per assignment is enforced by AssignmentInviteService inside
 * the send transaction.
 */
class AssignmentGroupInvite extends Model
{
    use BelongsToWorkspace;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'assignment_id',
        'group_id',
        'inviter_id',
        'invitee_id',
        'status',
        'responded_at',
        'expires_at',
        'workspace_id',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(AssignmentGroup::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitee_id');
    }

    /**
     * Still actionable? A pending row past its expiry counts as expired —
     * call {@see expireOverdue()} to persist that lazily.
     */
    public function isActionable(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        return ! $this->expires_at || ! $this->expires_at->isPast();
    }

    /**
     * Lazily flip pending invites whose due date has passed to expired.
     * Called on page loads and before invite reads/writes — no scheduler
     * needed. Returns the number of rows expired.
     */
    public static function expireOverdue(?Assignment $assignment = null): int
    {
        $query = self::query()
            ->where('status', self::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());

        if ($assignment !== null) {
            $query->where('assignment_id', $assignment->id);
        }

        return $query->update([
            'status' => self::STATUS_EXPIRED,
            'responded_at' => now(),
        ]);
    }
}
