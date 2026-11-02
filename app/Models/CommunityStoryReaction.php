<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityStoryReaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'story_id',
        'user_id',
        'reaction',
    ];

    public function story(): BelongsTo
    {
        return $this->belongsTo(CommunityStory::class, 'story_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
