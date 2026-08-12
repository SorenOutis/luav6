<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Support\RequestCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Season extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = ['name', 'start_date', 'end_date', 'is_active', 'show_countdown_on_welcome', 'admin_id'];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean',
        'show_countdown_on_welcome' => 'boolean',
    ];

    protected static function booted()
    {
        static::saving(function (Season $season) {
            if ($season->is_active) {
                // Deactivate all other seasons
                static::where('id', '!=', $season->id)->update(['is_active' => false]);
            }
        });

        // The active season may have changed — drop the memo so any later
        // Season::current() in this same request re-reads the database.
        //
        // Only the season keys are cleared (not the whole store): saving() above
        // can flip is_active on OTHER seasons too, so every scope's entry must
        // go, but unrelated memoized values must survive.
        static::saved(fn () => static::forgetAllCurrent());
        static::deleted(fn () => static::forgetAllCurrent());
    }

    public function progress()
    {
        return $this->hasMany(SeasonProgress::class);
    }

    public function sections()
    {
        return $this->hasMany(Section::class);
    }

    /**
     * The active season.
     *
     * Memoized for the lifetime of the request. This is called 3–5 times while
     * rendering a single dashboard (DashboardController, activeSeasonProgress(),
     * BadgeAwardService, ClaimXpService, the SectionProgress observers) and the
     * answer cannot change mid-request.
     *
     * The memo is per-scope: the `workspace` global scope makes this query
     * return a different row for each admin, so the key includes the acting
     * user's id and the store itself is flushed between requests by Octane.
     */
    public static function current()
    {
        return app(RequestCache::class)->remember(
            static::currentCacheKey(),
            fn () => self::where('is_active', true)->first()
        );
    }

    /**
     * Forget the memoized active season for the current user's scope.
     */
    public static function forgetCurrent(): void
    {
        app(RequestCache::class)->forget(static::currentCacheKey());
    }

    /**
     * Forget the memoized active season for every scope.
     *
     * Activating one season deactivates the others, which can change the answer
     * for admins other than the one performing the write, so all scopes are
     * invalidated rather than just the acting user's.
     */
    public static function forgetAllCurrent(): void
    {
        app(RequestCache::class)->forgetPrefix('season:current:');
    }

    private static function currentCacheKey(): string
    {
        return 'season:current:'.(auth()->id() ?? 'guest');
    }
}
