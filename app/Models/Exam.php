<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonInterface as Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Exam extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'title',
        'description',
        'exam_date',
        'starts_at',
        'ends_at',
        'duration_minutes',
        'xp_rewards_enabled',
        'completion_xp',
        'on_time_xp',
        'accuracy_xp_enabled',
        'status',
        'ai_feedback_enabled',
        'ai_feedback_enabled_at',
        'url',
        'section_id',
        'workspace_id',
        'admin_id',
    ];

    protected $casts = [
        'exam_date' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'xp_rewards_enabled' => 'boolean',
        'accuracy_xp_enabled' => 'boolean',
        'ai_feedback_enabled' => 'boolean',
        'ai_feedback_enabled_at' => 'datetime',
    ];

    protected static function booted()
    {
        // The migration leaves historical exams disabled to avoid retroactive
        // double rewards. Newly created exams opt in unless explicitly disabled.
        static::creating(function (Exam $exam) {
            if (! array_key_exists('xp_rewards_enabled', $exam->getAttributes())) {
                $exam->xp_rewards_enabled = true;
            }
        });

        static::updated(function ($exam) {
            Cache::forget("exam_structure_{$exam->id}");
        });

        static::deleted(function ($exam) {
            Cache::forget("exam_structure_{$exam->id}");
        });
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Whether the teacher set an explicit schedule window for this exam.
     */
    public function isScheduled(): bool
    {
        return $this->starts_at !== null || $this->ends_at !== null;
    }

    /**
     * Whether the exam window has begun.
     *
     * Legacy exams without a start time behave as if they were always open
     * from the moment they are published, so a missing value never locks an
     * existing exam out of nowhere.
     */
    public function hasStarted(?Carbon $now = null): bool
    {
        $now ??= now();

        return $this->starts_at === null || $this->starts_at->lte($now);
    }

    /**
     * Whether the exam window has finished.
     *
     * A missing end time means the exam stays open until a teacher closes it
     * manually, preserving pre-schedule behaviour for existing exams.
     */
    public function hasEnded(?Carbon $now = null): bool
    {
        $now ??= now();

        return $this->ends_at !== null && $this->ends_at->lte($now);
    }

    /**
     * Published exams only accept answers inside their open window.
     */
    public function acceptsSubmissions(?Carbon $now = null): bool
    {
        return $this->status === 'published'
            && $this->hasStarted($now)
            && ! $this->hasEnded($now);
    }

    /**
     * Student-facing lifecycle key for the current moment.
     *
     * A published exam that has not yet opened is "upcoming"; one still inside
     * its window is "open"; one whose window ended is "ended". The enum status
     * remains the admin-controlled lifecycle.
     */
    public function scheduleState(?Carbon $now = null): string
    {
        $now ??= now();

        if ($this->hasEnded($now)) {
            return 'ended';
        }

        if (! $this->hasStarted($now)) {
            return 'upcoming';
        }

        return 'open';
    }

    /**
     * Whether the exam is effectively closed for a student right now.
     *
     * Covers both a teacher manually closing it and the scheduled window
     * ending, so the student UI and answer-key reveal behave the same way.
     */
    public function isEffectivelyClosed(?Carbon $now = null): bool
    {
        return $this->status === 'closed' || $this->hasEnded($now);
    }

    /**
     * Every part of this exam, across all sets.
     *
     * Student-facing code must use App\Services\ExamSetAssignmentService
     * instead: a student only ever sees the parts of the set they were handed.
     */
    public function parts()
    {
        return $this->hasMany(ExamPart::class)->orderBy('sort_order');
    }

    /**
     * Interchangeable versions of this exam, in stored order.
     */
    public function sets(): HasMany
    {
        return $this->hasMany(ExamSet::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The set each student was handed (one row per student, at most).
     */
    public function setAssignments(): HasMany
    {
        return $this->hasMany(ExamSetAssignment::class);
    }

    /**
     * Students a teacher has barred from this exam.
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(ExamUserBlock::class);
    }

    /**
     * Same list as blocks(), as students, for the admin picker and the
     * exam-list badge.
     */
    public function blockedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'exam_user_blocks')
            ->withPivot(['blocked_by', 'reason'])
            ->withTimestamps();
    }

    /**
     * Whether a teacher has barred this student from the exam.
     *
     * Admins are never blocked: they audit every exam regardless of who the
     * teacher excluded.
     */
    public function isBlockedFor(User $user): bool
    {
        if ($user->is_admin) {
            return false;
        }

        return $this->blocks()
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Drop the exams this student has been barred from.
     *
     * Blocking is independent of the exam's status and of its schedule window:
     * a blocked student must not see the exam while it is a draft, while it is
     * still upcoming, while it is open, or after it closes — so this has to be
     * applied to every student-facing exam query, not only to the ones that
     * already filter on `status`.
     *
     * The pivot is read with `DB::table()` for the same reason
     * `scopeVisibleTo()` reads `section_user` that way: who is blocked is a
     * fact about the student and the exam, and must not be re-scoped by
     * tenant bookkeeping.
     */
    public function scopeNotBlockedBy(Builder $query, User $user): Builder
    {
        if ($user->is_admin) {
            return $query;
        }

        return $query->whereNotIn(
            $query->qualifyColumn('id'),
            DB::table('exam_user_blocks')
                ->where('user_id', $user->id)
                ->select('exam_id'),
        );
    }

    public function submissions()
    {
        return $this->hasMany(ExamSubmission::class);
    }

    public function xpAwards()
    {
        return $this->hasMany(ExamXpAward::class);
    }

    /**
     * Student-facing visibility, derived from enrollment rather than from the
     * active workspace context.
     *
     * An exam is visible to a student when it targets one of the sections they
     * are enrolled in, or when it is unassigned ("global") and either predates
     * tenants or belongs to a tenant that owns one of their sections.
     *
     * The workspace global scope is deliberately bypassed here: `section_user`
     * is the source of truth for what a student may see, while workspace
     * membership and `current_workspace_id` are tenant bookkeeping that can lag
     * behind enrollment (restored dumps, rows created before tenants shipped,
     * admin-assigned memberships) and may legitimately span several workspaces
     * for one student. Tying reads to the bookkeeping hid every exam from
     * enrolled students whenever the two drifted apart. Admins keep the
     * workspace-scoped tenant view untouched.
     *
     * Enrollment is only half of the rule: an exam a teacher has barred this
     * student from is dropped here as well, whatever its status or schedule.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->is_admin) {
            return $query;
        }

        $sectionIds = DB::table('section_user')
            ->where('user_id', $user->id)
            ->pluck('section_id');

        $workspaceIds = DB::table('sections')
            ->whereIn('id', $sectionIds)
            ->whereNotNull('workspace_id')
            ->pluck('workspace_id')
            ->unique()
            ->values();

        return $query
            ->withoutGlobalScope('workspace')
            ->notBlockedBy($user)
            ->where(function (Builder $query) use ($sectionIds, $workspaceIds): void {
                $query->whereIn('section_id', $sectionIds)
                    ->orWhere(function (Builder $query) use ($workspaceIds): void {
                        $query->whereNull('section_id')
                            ->where(function (Builder $query) use ($workspaceIds): void {
                                $query->whereNull('workspace_id')
                                    ->orWhereIn('workspace_id', $workspaceIds);
                            });
                    });
            });
    }

    /**
     * Route binding must not depend on the viewer's workspace bookkeeping:
     * students resolve exams across every tenant they are enrolled in (they
     * may span several), and authorization itself is enforced per action via
     * assertCanAccess(). Admins keep the workspace-scoped tenant view.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $user = auth()->user();

        if (! $user || $user->is_admin) {
            return parent::resolveRouteBinding($value, $field);
        }

        return static::withoutGlobalScope('workspace')
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();
    }
}
