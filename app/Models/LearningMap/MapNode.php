<?php

namespace App\Models\LearningMap;

use App\Models\Badge;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MapNode extends Model
{
    protected $fillable = [
        'map_world_id', 'slug', 'title', 'type', 'x', 'y', 'pass_score',
        'target_type', 'target_id',
        'reward_xp', 'reward_points', 'reward_badge_id',
    ];

    protected $casts = [
        'x' => 'integer',
        'y' => 'integer',
        'pass_score' => 'integer',
        'reward_xp' => 'integer',
        'reward_points' => 'integer',
    ];

    public function effectivePassScore(): int
    {
        return $this->pass_score ?? (int) config('gamification.map_node_default_pass_score', 70);
    }

    public function world(): BelongsTo
    {
        return $this->belongsTo(MapWorld::class, 'map_world_id');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(MapNodeRequirement::class);
    }

    public function rewardBadge(): BelongsTo
    {
        return $this->belongsTo(Badge::class, 'reward_badge_id');
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
