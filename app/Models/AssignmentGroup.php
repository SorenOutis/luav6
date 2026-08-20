<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A student-formed group for a single assignment (group activity).
 *
 * The group only holds identity (assignment + creator). The shared submission
 * is mirrored onto every member's `assignment_user` row, so the student page,
 * the admin submissions table and the grading/award hooks keep working without
 * knowing about groups at all.
 */
class AssignmentGroup extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'assignment_id',
        'created_by',
        'workspace_id',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The members' submission rows (one per member, on `assignment_user`).
     */
    public function members(): HasMany
    {
        return $this->hasMany(Submission::class, 'group_id');
    }
}
