<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Workspace extends Model
{
    use HasFactory;

    public const ROLE_OWNER = 'owner';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_STUDENT = 'student';

    protected $fillable = ['name', 'slug', 'created_by'];

    protected $casts = [
        'archived_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Workspace $workspace): void {
            $workspace->public_id ??= (string) Str::uuid7();

            if (! $workspace->slug) {
                $base = Str::slug($workspace->name) ?: 'workspace';
                $slug = $base;
                $suffix = 2;

                while (static::query()->where('slug', $slug)->exists()) {
                    $slug = "{$base}-{$suffix}";
                    $suffix++;
                }

                $workspace->slug = $slug;
            }
        });
    }

    public static function createForOwner(User $owner, ?string $name = null): self
    {
        return DB::transaction(function () use ($owner, $name): self {
            $workspace = static::query()->create([
                'name' => $name ?: "{$owner->name}'s Workspace",
                'created_by' => $owner->id,
            ]);

            $workspace->users()->attach($owner->id, ['role' => self::ROLE_OWNER]);
            $owner->forceFill(['current_workspace_id' => $workspace->id])->save();

            return $workspace;
        });
    }

    public function archive(User $actor): void
    {
        if ((int) $actor->current_workspace_id === (int) $this->id) {
            $hasReplacement = $actor->workspaces()
                ->whereNull('workspaces.archived_at')
                ->whereKeyNot($this->id)
                ->exists();

            abort_unless(
                $hasReplacement,
                422,
                'Assign another active workspace before archiving your current one.',
            );
        }

        DB::transaction(function () use ($actor): void {
            $this->forceFill([
                'archived_at' => now(),
                'archived_by' => $actor->id,
            ])->save();

            foreach ($this->users()->get(['users.id', 'current_workspace_id']) as $user) {
                if ((int) $user->current_workspace_id !== (int) $this->id) {
                    continue;
                }

                $replacement = $user->workspaces()
                    ->whereNull('workspaces.archived_at')
                    ->whereKeyNot($this->id)
                    ->orderBy('workspaces.id')
                    ->value('workspaces.id');
                $user->forceFill(['current_workspace_id' => $replacement])->save();
            }
        });
    }

    public function restore(): void
    {
        $this->forceFill([
            'archived_at' => null,
            'archived_by' => null,
        ])->save();
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function archiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function admins(): BelongsToMany
    {
        return $this->users()->wherePivotIn('role', [self::ROLE_OWNER, self::ROLE_ADMIN]);
    }

    public function students(): BelongsToMany
    {
        return $this->users()->wherePivot('role', self::ROLE_STUDENT);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    public function pendingAiActions(): HasMany
    {
        return $this->hasMany(PendingAiAction::class);
    }

    public function aiBudgetPeriods(): HasMany
    {
        return $this->hasMany(AiBudgetPeriod::class);
    }

    public function aiBudgetReservations(): HasMany
    {
        return $this->hasMany(AiBudgetReservation::class);
    }

    public function aiBudgetEvents(): HasMany
    {
        return $this->hasMany(AiBudgetEvent::class);
    }

    public function aiEssayFeedbackDrafts(): HasMany
    {
        return $this->hasMany(AiEssayFeedbackDraft::class);
    }

    public function aiReviewEvents(): HasMany
    {
        return $this->hasMany(AiReviewEvent::class);
    }
}
