<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunityComment extends Model
{
    protected $fillable = [
        'post_id',
        'post_media_id',
        'parent_id',
        'user_id',
        'content',
        'likes_count',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(CommunityPost::class, 'post_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(CommunityPostMedia::class, 'post_media_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(CommunityComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(CommunityComment::class, 'parent_id')->oldest();
    }

    public function likes(): HasMany
    {
        return $this->hasMany(CommunityCommentLike::class, 'comment_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (CommunityComment $comment): void {
            // Database cascades skip model events, so delete descendants explicitly for scoped counters.
            foreach ($comment->replies()->get() as $reply) {
                $reply->delete();
            }

            $target = $comment->post_media_id !== null
                ? CommunityPostMedia::find($comment->post_media_id)
                : CommunityPost::withoutGlobalScopes()->find($comment->post_id);

            if ($target && $target->comments_count > 0) {
                $target->decrement('comments_count');
            }
        });
    }
}
