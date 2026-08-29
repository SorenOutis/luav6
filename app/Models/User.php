<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\StudentNotificationService;
use App\Support\PublicFileUrl;
use App\Support\WorkspaceContext;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    public const PROFILE_VISIBILITY_SECTION = 'section';

    public const PROFILE_VISIBILITY_PRIVATE = 'private';

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (Schema::hasColumn($user->getTable(), 'public_id') && ! $user->public_id) {
                $user->public_id = (string) Str::uuid7();
            }
        });

        static::created(function (User $user): void {
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

    public function canImpersonate(): bool
    {
        return (bool) ($this->is_admin || $this->is_super_admin);
    }

    public function canBeImpersonated(): bool
    {
        if ($this->is_admin || $this->is_banned) {
            return false;
        }

        $actor = auth()->user();

        if (! $actor instanceof self || $actor->is($this) || ! $actor->canImpersonate()) {
            return false;
        }

        if ($actor->isSuperAdmin()) {
            return true;
        }

        $workspaceId = app(WorkspaceContext::class)->id();

        return $this->sections()
            ->when($workspaceId, fn ($query) => $query->where('sections.workspace_id', $workspaceId))
            ->when(! $workspaceId, fn ($query) => $query->whereNull('sections.workspace_id'))
            ->exists();
    }

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
        'onboarding_tours',
    ];

    public function isSuperAdmin(): bool
    {
        return $this->is_admin && $this->is_super_admin;
    }

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

    protected function composeName(): string
    {
        return trim(implode(' ', array_filter([
            $this->attributes['first_name'] ?? '',
            $this->attributes['middle_name'] ?? '',
            $this->attributes['last_name'] ?? '',
        ])));
    }

    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = $value;

        [$first, $middle, $last] = $this->splitName($value);

        $this->attributes['first_name'] = $first;
        $this->attributes['middle_name'] = $middle;
        $this->attributes['last_name'] = $last;
    }

    public function setFirstNameAttribute($value): void
    {
        $this->attributes['first_name'] = $value;
        $this->attributes['name'] = $this->composeName();
    }

    public function setMiddleNameAttribute($value): void
    {
        $this->attributes['middle_name'] = $value;
        $this->attributes['name'] = $this->composeName();
    }

    public function setLastNameAttribute($value): void
    {
        $this->attributes['last_name'] = $value;
        $this->attributes['name'] = $this->composeName();
    }

    public function socialAccounts()
    {
        return $this->hasMany(SocialAccount::class);
    }

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

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

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
            'onboarding_tours' => 'array',
        ];
    }

    /**
     * Onboarding tours this account has already resolved, keyed by tour id
     * with a value of 'done' or 'skipped'.
     *
     * @return array<string, string>
     */
    public function onboardingTours(): array
    {
        $tours = $this->onboarding_tours;

        if (! is_array($tours)) {
            return [];
        }

        return array_filter(
            $tours,
            fn ($status, $tourId) => is_string($tourId)
                && in_array($status, ['done', 'skipped'], true),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * Record a tour as finished/skipped. Once set, the tour never auto-starts
     * again on any device — the first resolution wins, so a later visit can't
     * downgrade a 'done' to 'skipped' or replay the walkthrough.
     */
    public function markOnboardingTour(string $tourId, string $status): void
    {
        if (! in_array($status, ['done', 'skipped'], true)) {
            return;
        }

        $tours = $this->onboardingTours();

        if (isset($tours[$tourId])) {
            return;
        }

        $tours[$tourId] = $status;

        $this->forceFill(['onboarding_tours' => $tours])->save();
    }

    /** Clear one tour (or all of them) so it can play again. */
    public function resetOnboardingTour(?string $tourId = null): void
    {
        $tours = $this->onboardingTours();

        if ($tourId === null) {
            $tours = [];
        } else {
            unset($tours[$tourId]);
        }

        $this->forceFill(['onboarding_tours' => $tours])->save();
    }

    public function badges()
    {
        return $this->belongsToMany(Badge::class)->withPivot('season_id')->withTimestamps();
    }

    public function following()
    {
        return $this->belongsToMany(self::class, 'user_follows', 'follower_id', 'followed_id')
            ->withTimestamps();
    }

    public function followers()
    {
        return $this->belongsToMany(self::class, 'user_follows', 'followed_id', 'follower_id')
            ->withTimestamps();
    }

    public function courses()
    {
        return $this->belongsToMany(Course::class)
            ->using(CourseUser::class)
            ->withPivot('completed_lessons', 'xp_earned', 'next_deadline')
            ->withTimestamps();
    }

    public function assignments()
    {
        return $this->belongsToMany(Assignment::class)->withPivot('submitted', 'status', 'grade', 'file_path', 'submitted_at', 'points', 'xp_earned', 'feedback', 'graded_at', 'graded_by', 'group_id', 'submitted_by')->withTimestamps();
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }

    public function examSubmissions()
    {
        return $this->hasMany(ExamSubmission::class);
    }

    public function rewards()
    {
        return $this->belongsToMany(Reward::class);
    }

    public function getAvatarAttribute($value)
    {
        return PublicFileUrl::resolve($value);
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar;
    }

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

    public function grades()
    {
        return $this->hasMany(Grade::class);
    }

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

    public function getCoverPhotoAttribute($value)
    {
        return PublicFileUrl::resolve($value);
    }

    public function notifyBell(): void
    {
        if (class_exists('Benriadh1\\FilamentNotificationBell\\Events\\NotificationSent')) {
            $eventClass = 'Benriadh1\\FilamentNotificationBell\\Events\\NotificationSent';
            event(new $eventClass($this));
        }
    }
}
