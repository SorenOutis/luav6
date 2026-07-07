<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class Season extends Model
{
    use BelongsToWorkspace;

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
    }

    public function progress()
    {
        return $this->hasMany(SeasonProgress::class);
    }

    public static function current()
    {
        return self::where('is_active', true)->first();
    }
}
