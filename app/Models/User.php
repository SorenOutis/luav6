<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

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
        'current_streak',
        'longest_streak',
        'last_login_at',
        'avatar',
        'cover_photo',
    ];

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
                "Enrolled in Section: " . ($section?->name ?? 'Unknown'),
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
        ];
    }

    /**
     * Get the badges associated with the user.
     */
    public function badges()
    {
        return $this->belongsToMany(Badge::class);
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

        return asset('storage/'.$value);
    }

    /**
     * Get the section associated with the user.
     */
    public function sections()
    {
        return $this->belongsToMany(Section::class);
    }

    public function gamificationHistories()
    {
        return $this->hasMany(GamificationHistory::class);
    }

    public function recordGamificationHistory($amountXp, $amountPoints, $reason, $description = null, $sectionId = null, $seasonId = null)
    {
        if (abs($amountXp) < 0.001 && abs($amountPoints) < 0.001) {
            return null;
        }

        if (!$seasonId) {
            $seasonId = Season::current()?->id;
        }

        return $this->gamificationHistories()->create([
            'amount_xp' => $amountXp,
            'amount_points' => $amountPoints,
            'reason' => $reason,
            'description' => $description,
            'section_id' => $sectionId,
            'season_id' => $seasonId,
        ]);
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

        return asset('storage/'.$value);
    }
}
