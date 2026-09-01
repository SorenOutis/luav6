<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
