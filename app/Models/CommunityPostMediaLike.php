<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityPostMediaLike extends Model
{
    protected $fillable = [
        'post_media_id',
        'user_id',
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(CommunityPostMedia::class, 'post_media_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
