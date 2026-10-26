<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class CommunityPostMedia extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'media_url',
        'media_type',
        'file_name',
        'file_size',
        'sort_order',
        'likes_count',
        'comments_count',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'sort_order' => 'integer',
            'likes_count' => 'integer',
            'comments_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (CommunityPostMedia $media): void {
            if ($media->media_url) {
                Storage::disk('public')->delete($media->media_url);
            }
        });
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(CommunityPost::class, 'post_id');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(CommunityPostMediaLike::class, 'post_media_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(CommunityComment::class, 'post_media_id');
    }
}
