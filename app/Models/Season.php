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

        // The active season just changed — drop the per-request memo so any
        // later Season::current() in this same request re-reads the database.
        static::saved(fn () => app(RequestCache::class)->forget());
        static::deleted(fn () => app(RequestCache::class)->forget());
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
        $scope = auth()->id() ?? 'guest';

        return app(RequestCache::class)->remember(
            "season:current:{$scope}",
            fn () => self::where('is_active', true)->first()
        );
    }

    /**
     * Forget the memoized active season.
     *
     * Needed when a season is activated/deactivated inside a request that then
     * goes on to read Season::current() again.
     */
    public static function forgetCurrent(): void
    {
        app(RequestCache::class)->forget('season:current:'.(auth()->id() ?? 'guest'));
    }
}
