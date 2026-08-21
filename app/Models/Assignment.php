<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Assignment extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['title', 'description', 'due_date', 'course_id', 'workspace_id', 'admin_id', 'points_possible', 'min_group_size', 'max_group_size'];

    protected $casts = [
        'due_date' => 'datetime',
        'points_possible' => 'decimal:2',
        'min_group_size' => 'integer',
        'max_group_size' => 'integer',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
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
     * Limit the query to assignments targeted at any of the given sections.
     *
     * @param  Collection<int, int>|array<int, int>  $sectionIds
     */
    public function scopeVisibleToSections(Builder $query, Collection|array $sectionIds): Builder
    {
        $sectionIds = collect($sectionIds)->filter()->unique()->values();

        if ($sectionIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas(
            'sections',
            fn (Builder $sections) => $sections->whereIn('sections.id', $sectionIds)
        );
    }

    /**
     * Whether the given user is in one of this assignment's target sections.
     */
    public function isVisibleTo(User $user): bool
    {
        if ($user->is_admin) {
            return true;
        }

        return $this->sections()
            ->whereIn('sections.id', $user->sections()->pluck('sections.id'))
            ->exists();
    }
}
