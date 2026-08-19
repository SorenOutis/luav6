<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\StudentNotificationService;
use App\Support\PublicFileUrl;
use App\Support\WorkspaceContext;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements FilamentUser
{
    public const PROFILE_VISIBILITY_SECTION = 'section';

    public const PROFILE_VISIBILITY_PRIVATE = 'private';

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            // Current model code also runs inside historical data migrations on
            // a fresh install, before the public_id migration has executed.
            if (Schema::hasColumn($user->getTable(), 'public_id') && ! $user->public_id) {
                $user->public_id = (string) Str::uuid7();
            }
        });

        static::created(function (User $user): void {
            // Existing super admins are backfilled by the workspace migration;
            // this covers super-admin accounts created after deployment.
            if (
                $user->isSuperAdmin()
                && Schema::hasTable('workspaces')
                && $user->workspaces()->doesntExist()
            ) {
                Workspace::createForOwner($user);
            }
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) ($this->is_admin || $this->is_super_admin);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'password',
        'points',
        'exp',
        'level',
        'is_admin',
        'is_super_admin',
        'is_banned',
        'banned_at',
        'ban_reason',
        'current_streak',
        'longest_streak',
        'last_login_at',
        'last_claimed_at',
        'avatar',
        'cover_photo',
        'bio',
        'profile_visibility',
        'profile_show_activity',
        'profile_show_sections',
        'profile_show_social',
        'profile_show_achievements',
        'blur_leaderboard',
    ];

    /**
     * Determine if the user is a super admin who can see all workspaces.
     */
    public function isSuperAdmin(): bool
    {
        return $this->is_admin && $this->is_super_admin;
    }

    /**
     * Split a full name into first / middle / last parts.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    protected function splitName(?string $value): array
    {
        $parts = array_values(array_filter(
            preg_split('/\s+/', trim((string) $value)) ?: [],
            static fn ($part): bool => $part !== ''
        ));

        if (count($parts) === 0) {
            return ['', '', ''];
        }

        if (count($parts) === 1) {
            return [$parts[0], '', ''];
        }

        $first = $parts[0];
        $last = $parts[count($parts) - 1];
        $middle = count($parts) > 2
            ? implode(' ', array_slice($parts, 1, -1))
            : '';

        return [$first, $middle, $last];
    }

    /**
     * Compose the full name from the three name parts.
     */
    protected function composeName(): string
    {
        return trim(implode(' ', array_filter([
            $this->attributes['first_name'] ?? '',
            $this->attributes['middle_name'] ?? '',
            $this->attributes['last_name'] ?? '',
        ])));
    }

    /**
     * Keep the name parts in sync when the full name is assigned directly
     * (legacy paths such as factories, seeders, and CLI commands).
     */
    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = $value;

        [$first, $middle, $last] = $this->splitName($value);

        $this->attributes['first_name'] = $first;
        $this->attributes['middle_name'] = $middle;
        $this->attributes['last_name'] = $last;
    }

    /**
     * Keep the full name column in sync when the first name changes.
     */
    public function setFirstNameAttribute($value): void
    {
        $this->attributes['first_name'] = $value;
        $this->attributes['name'] = $this->composeName();
    }

    /**
     * Keep the full name column in sync when the middle name changes.
     */
    public function setMiddleNameAttribute($value): void
    {
        $this->attributes['middle_name'] = $value;
        $this->attributes['name'] = $this->composeName();
    }

    /**
     * Keep the full name column in sync when the last name changes.
     */
    public function setLastNameAttribute($value): void
    {
        $this->attributes['last_name'] = $value;
        $this->attributes['name'] = $this->composeName();
    }

    /**
     * Google / GitHub identities linked to this account.
     */
    public function socialAccounts()
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * Whether the account can sign in with a password.
     *
     * Accounts created through social login start without one, so password
     * based flows (and unlinking the last provider) must check this first.
     */
    public function hasPassword(): bool
    {
        return filled($this->getAuthPassword());
    }

    public function seasonProgress()
    {
        return $this->hasMany(SeasonProgress::class);
    }

    public function currentSeasonProgress()
    {
        $currentSeason = Season::where('is_active', true)->first() ?? Season::first();
        $seasonId = $currentSeason?->id;

        return $this->hasOne(SeasonProgress::class)
            ->where('season_id', $seasonId);
    }

    public function activeSeasonProgress()
    {
        $currentSeason = Season::current();
        if (! $currentSeason) {
            return null;
        }

        return $this->seasonProgress()->firstOrCreate(
            ['season_id' => $currentSeason->id],
            ['exp' => 0, 'level' => 1, 'points' => 0]
        );
    }

    public function sectionProgress()
    {
        return $this->hasMany(SectionProgress::class);
    }

    public function activeSectionProgress($sectionId)
    {
        $section = Section::find($sectionId);
        $rewardExp = (float) ($section?->reward_exp ?? 0);
        $rewardPoints = (float) ($section?->reward_points ?? 0);

        $progress = $this->sectionProgress()->firstOrCreate(
            ['section_id' => $sectionId],
            ['exp' => $rewardExp, 'points' => $rewardPoints]
        );

        if ($progress->wasRecentlyCreated && ($rewardExp > 0 || $rewardPoints > 0)) {
            $this->recordGamificationHistory(
                $rewardExp,
                $rewardPoints,
                'Section Enrollment',
                'Enrolled in Section: '.($section?->name ?? 'Unknown'),
                $sectionId
            );
        }

        return $progress;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_claimed_at' => 'datetime',
            'is_admin' => 'boolean',
            'is_super_admin' => 'boolean',
            'is_banned' => 'boolean',
            'blur_leaderboard' => 'boolean',
            'profile_show_activity' => 'boolean',
            'profile_show_sections' => 'boolean',
            'profile_show_social' => 'boolean',
            'profile_show_achievements' => 'boolean',
            'banned_at' => 'datetime',
        ];
    }

    /**
     * Get the badges associated with the user.
     */
    public function badges()
    {
        return $this->belongsToMany(Badge::class)->withPivot('season_id')->withTimestamps();
    }

    /** Users this student follows. */
    public function following()
    {
        return $this->belongsToMany(self::class, 'user_follows', 'follower_id', 'followed_id')
            ->withTimestamps();
    }

    /** Users following this student. */
    public function followers()
    {
        return $this->belongsToMany(self::class, 'user_follows', 'followed_id', 'follower_id')
            ->withTimestamps();
    }

    /**
     * Get the courses associated with the user.
     */
    public function courses()
    {
        return $this->belongsToMany(Course::class)
            ->using(CourseUser::class)
            ->withPivot('completed_lessons', 'xp_earned', 'next_deadline')
            ->withTimestamps();
    }

    /**
     * Get the assignments associated with the user.
     */
    public function assignments()
    {
        return $this->belongsToMany(Assignment::class)->withPivot('submitted', 'status', 'grade', 'file_path', 'submitted_at')->withTimestamps();
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }

    public function examSubmissions()
    {
        return $this->hasMany(ExamSubmission::class);
    }

    /**
     * Get the rewards associated with the user.
     */
    public function rewards()
    {
        return $this->belongsToMany(Reward::class);
    }

    /**
     * Get the user's avatar URL.
     *
     * @return string|null
     */
    public function getAvatarAttribute($value)
    {
        return PublicFileUrl::resolve($value);
    }

    /**
     * Get the section associated with the user.
     */
    public function sections()
    {
        return $this->belongsToMany(Section::class)
            ->using(SectionUser::class)
            ->withPivot('season_id');
    }

    public function workspaces()
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function currentWorkspace()
    {
        return $this->belongsTo(Workspace::class, 'current_workspace_id');
    }

    public function activateWorkspace(Workspace $workspace): void
    {
        abort_if($workspace->isArchived(), 422, 'Archived workspaces cannot be activated.');
        abort_unless(
            $this->isSuperAdmin() || $this->workspaces()->whereKey($workspace->id)->exists(),
            403,
        );

        $this->forceFill(['current_workspace_id' => $workspace->id])->save();
        app(WorkspaceContext::class)->set($workspace);
    }

    public function joinWorkspace(int $workspaceId, string $role = Workspace::ROLE_STUDENT): void
    {
        if (! $this->workspaces()->whereKey($workspaceId)->exists()) {
            $this->workspaces()->attach($workspaceId, ['role' => $role]);
        }
        $this->forceFill(['current_workspace_id' => $workspaceId])->save();
        app(WorkspaceContext::class)->set($workspaceId);
    }

    /**
     * Grades the user (student) has received.
     */
    public function grades()
    {
        return $this->hasMany(Grade::class);
    }

    /**
     * Grades the user (admin/teacher) has recorded for others.
     */
    public function recordedGrades()
    {
        return $this->hasMany(Grade::class, 'recorded_by');
    }

    public function gamificationHistories()
    {
        return $this->hasMany(GamificationHistory::class);
    }

    public function dailyXpClaims()
    {
        return $this->hasMany(DailyXpClaim::class);
    }

    public function bonusXpClaims()
    {
        return $this->hasMany(BonusXpClaim::class);
    }

    public function chatSessions()
    {
        return $this->hasMany(ChatSession::class)->orderByDesc('updated_at');
    }

    public function pendingAiActions()
    {
        return $this->hasMany(PendingAiAction::class);
    }

    /**
     * Scope the query to only include users in the current admin's workspace.
     * Super admins see all non-admin users; regular admins only see students
     * enrolled in their sections.
     */
    public function scopeForWorkspace(Builder $query): Builder
    {
        $user = auth()->user();

        $context = app(WorkspaceContext::class);

        if (! $user || ! $user->is_admin) {
            return $query;
        }

        if ($user->isSuperAdmin() && ! $context->isInspecting()) {
            return $query;
        }

        $workspaceId = $context->id();

        // Every administrator in the tenant sees the same student roster.
        return $query->whereHas('sections', fn ($q) => $q
            ->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId))
            ->when(! $workspaceId, fn ($q) => $q->whereNull('workspace_id')));
    }

    public function recordGamificationHistory($amountXp, $amountPoints, $reason, $description = null, $sectionId = null, $seasonId = null, $awardedBy = null, $notify = true)
    {
        if (abs($amountXp) < 0.001 && abs($amountPoints) < 0.001) {
            return null;
        }

        if (! $seasonId) {
            $seasonId = Season::current()?->id;
        }

        $history = $this->gamificationHistories()->create([
            'awarded_by' => $awardedBy,
            'amount_xp' => $amountXp,
            'amount_points' => $amountPoints,
            'reason' => $reason,
            'description' => $description,
            'section_id' => $sectionId,
            'season_id' => $seasonId,
        ]);

        // Back-office adjustments (admin/teacher manual tweaks) are recorded
        // for audit but are not surfaced to the student, so we don't push an
        // XP notification for them.
        if ($notify) {
            app(StudentNotificationService::class)->sendXpEarned(
                $this,
                (float) $amountXp,
                (string) $reason,
                $description
            );
        }

        return $history;
    }

    /**
     * Get the user's cover photo URL.
     *
     * @return string|null
     */
    public function getCoverPhotoAttribute($value)
    {
        return PublicFileUrl::resolve($value);
    }
}
