<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
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
        if (!$currentSeason)
            return null;

        return $this->seasonProgress()->firstOrCreate(
            ['season_id' => $currentSeason->id],
            ['exp' => 0, 'level' => 1, 'points' => 0]
        );
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
        return $this->belongsToMany(Assignment::class)->withPivot('submitted', 'status', 'grade')->withTimestamps();
    }
    /**
     * Get the rewards associated with the user.
     */
    public function rewards()
    {
        return $this->belongsToMany(Reward::class);
    }
}
