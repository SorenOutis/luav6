<?php

namespace App\Models\LearningMap;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMapNodeProgress extends Model
{
    protected $table = 'user_map_node_progress';

    protected $fillable = [
        'user_id', 'map_node_id', 'status', 'score', 'completed_at',
    ];

    protected $casts = [
        'score' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(MapNode::class, 'map_node_id');
    }
}
