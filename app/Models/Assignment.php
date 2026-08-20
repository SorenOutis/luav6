<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class Assignment extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['title', 'description', 'due_date', 'course_id', 'workspace_id', 'admin_id'];

    protected $casts = [
        'due_date' => 'datetime',
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
        return $this->belongsToMany(User::class)->withPivot('submitted', 'status', 'grade', 'file_path', 'submitted_at', 'points', 'xp_earned', 'feedback', 'graded_at', 'graded_by')->withTimestamps();
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
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
