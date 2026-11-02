<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityPostLike extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'post_id',
        'user_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(CommunityPost::class, 'post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (CommunityPostLike $like): void {
            $post = CommunityPost::withoutGlobalScopes()->find($like->post_id);
            if ($post && $post->likes_count > 0) {
                $post->decrement('likes_count');
            }
        });
    }
}
