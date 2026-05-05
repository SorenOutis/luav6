<?php

namespace App\Models\LearningMap;

use App\Models\Badge;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MapNodeRequirement extends Model
{
    public const KIND_NODE = 'node';

    public const KIND_XP = 'xp';

    public const KIND_LEVEL = 'level';

    public const KIND_BADGE = 'badge';

    public const KIND_STREAK = 'streak';

    protected $fillable = [
        'map_node_id', 'kind',
        'target_node_slug', 'amount', 'level', 'badge_id', 'min_score',
    ];

    protected $casts = [
        'amount' => 'integer',
        'level' => 'integer',
        'min_score' => 'integer',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(MapNode::class, 'map_node_id');
    }

    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }
}
