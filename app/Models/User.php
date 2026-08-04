<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\StudentNotificationService;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

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
        'blur_leaderboard',
    ];

    /**
     * Determine if the user is a super admin who can see all workspaces.
     */
    public function isSuperAdmin(): bool
    {
        return $this->is_admin && $this->is_super_admin;
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

    /**
     * Get the courses associated with the user.
     */
    public function courses()
    {
        return $this->belongsToMany(Course::class)->withPivot('completed_lessons', 'xp_earned', 'next_deadline')->withTimestamps();
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
        if (! $value) {
            return null;
        }

        return Storage::disk('public')->url($value);
    }

    /**
     * Get the section associated with the user.
     */
    public function sections()
    {
        return $this->belongsToMany(Section::class)->withPivot('season_id');
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

    /**
     * Scope the query to only include users in the current admin's workspace.
     * Super admins see all non-admin users; regular admins only see students
     * enrolled in their sections.
     */
    public function scopeForWorkspace(Builder $query): Builder
    {
        $user = auth()->user();

        // Super admin sees all non-admin users
        if (! $user || ! $user->is_admin || $user->isSuperAdmin()) {
            return $query;
        }

        // Regular admin sees only students enrolled in their sections
        return $query->whereHas('sections', fn ($q) => $q->where('admin_id', $user->id));
    }

    public function recordGamificationHistory($amountXp, $amountPoints, $reason, $description = null, $sectionId = null, $seasonId = null)
    {
        if (abs($amountXp) < 0.001 && abs($amountPoints) < 0.001) {
            return null;
        }

        if (! $seasonId) {
            $seasonId = Season::current()?->id;
        }

        $history = $this->gamificationHistories()->create([
            'amount_xp' => $amountXp,
            'amount_points' => $amountPoints,
            'reason' => $reason,
            'description' => $description,
            'section_id' => $sectionId,
            'season_id' => $seasonId,
        ]);

        app(StudentNotificationService::class)->sendXpEarned(
            $this,
            (float) $amountXp,
            (string) $reason,
            $description
        );

        return $history;
    }

    /**
     * Get the user's cover photo URL.
     *
     * @return string|null
     */
    public function getCoverPhotoAttribute($value)
    {
        if (! $value) {
            return null;
        }

        return Storage::disk('public')->url($value);
    }
}
