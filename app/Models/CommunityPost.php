<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class CommunityPost extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'section_id',
        'profile_music_track_id',
        'content',
        'attachment_url',
        'attachment_type',
        'attachment_name',
        'attachment_size',
        'is_anonymous',
        'is_hidden',
        'likes_count',
        'comments_count',
    ];

    protected function casts(): array
    {
        return [
            'is_anonymous' => 'boolean',
            'is_hidden' => 'boolean',
            'likes_count' => 'integer',
            'comments_count' => 'integer',
            'attachment_size' => 'integer',
            'profile_music_track_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (CommunityPost $post): void {
            if ($post->attachment_url) {
                Storage::disk('public')->delete($post->attachment_url);
            }

            foreach ($post->media as $mediaItem) {
                $mediaItem->delete();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(CommunityPostLike::class, 'post_id');
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(CommunityPostBookmark::class, 'post_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(CommunityComment::class, 'post_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(CommunityPostMedia::class, 'post_id')->orderBy('sort_order', 'asc');
    }

    public function musicTrack(): BelongsTo
    {
        return $this->belongsTo(ProfileMusicTrack::class, 'profile_music_track_id');
    }
}
