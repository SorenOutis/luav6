<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Assignment extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['title', 'description', 'due_date', 'course_id', 'workspace_id', 'admin_id', 'points_possible', 'min_group_size', 'max_group_size', 'is_active', 'status'];

    protected $attributes = [
        'is_active' => true,
        'status' => AssignmentStatus::Published->value,
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'points_possible' => 'decimal:2',
        'min_group_size' => 'integer',
        'max_group_size' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * The lifecycle status as an enum.
     *
     * The attribute itself stays a plain string (as Exam::status does) so the
     * Filament select and table badge work without enum casts; code paths use
     * this helper for the typed behavior. Missing or unknown values behave as
     * "no status" and are treated as hidden at the call sites.
     */
    public function status(): ?AssignmentStatus
    {
        return AssignmentStatus::tryFrom((string) $this->getAttribute('status'));
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Due date for the browser, as a wall-clock value with NO timezone offset.
     *
     * The admin's Filament DateTimePicker is timezone-naive: whatever the
     * admin enters ("Aug 25, 2:30 PM") is meant to be seen by every student
     * verbatim. Shipping an offset here (e.g. toIso8601String() →
     * "2026-08-25T14:30:00+00:00") makes the browser treat it as an absolute
     * instant and convert it into each student's local zone, shifting the
     * displayed time by the offset. A bare ISO datetime ("2026-08-25T14:30:00")
     * is parsed by the browser as local time, so it renders exactly as the
     * admin entered it and matches the admin Filament table.
     */
    public function dueDateForClient(): ?string
    {
        return $this->due_date?->format('Y-m-d\TH:i:s');
    }

    /**
     * The sections this assignment is given to.
     *
     * An assignment with no sections is unassigned: it is visible to nobody
     * on the student side until the admin targets at least one section.
     */
    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class)->withTimestamps();
    }

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('submitted', 'status', 'grade', 'file_path', 'submitted_at', 'points', 'xp_earned', 'feedback', 'graded_at', 'graded_by', 'group_id', 'submitted_by')->withTimestamps();
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }

    /**
     * Student-formed groups for this assignment (group activities).
     */
    public function groups(): HasMany
    {
        return $this->hasMany(AssignmentGroup::class);
    }

    /**
     * Limit the query to assignments students may see (published and closed;
     * drafts stay hidden).
     */
    public function scopeVisibleToStudents(Builder $query): Builder
    {
        return $query->where('status', '!=', AssignmentStatus::Draft);
    }

    /**
     * Limit the query to student-visible assignments targeted at any of the
     * given sections.
     *
     * @param  Collection<int, int>|array<int, int>  $sectionIds
     */
    public function scopeVisibleToSections(Builder $query, Collection|array $sectionIds): Builder
    {
        $sectionIds = collect($sectionIds)->filter()->unique()->values();

        if ($sectionIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->visibleToStudents()
            ->whereHas(
                'sections',
                fn (Builder $sections) => $sections->whereIn('sections.id', $sectionIds)
            );
    }

    /**
     * Whether the given user can access this assignment. Administrators retain
     * access so they can manage closed work. Drafts are hidden from students
     * entirely; closed ones stay visible but read-only (see
     * AssignmentStatus::acceptsSubmissions()).
     */
    public function isVisibleTo(User $user): bool
    {
        if ($user->is_admin) {
            return true;
        }

        $status = $this->status();

        if ($status === null || ! $status->isVisibleToStudents()) {
            return false;
        }

        return $this->sections()
            ->whereIn('sections.id', $user->sections()->pluck('sections.id'))
            ->exists();
    }
}
