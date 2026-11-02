<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CommunityProfileFeatured extends Model
{
    use HasFactory;

    protected $table = 'community_profile_featured';

    protected $fillable = [
        'user_id',
        'media_url',
        'media_type',
        'thumbnail_url',
        'title',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (CommunityProfileFeatured $featured): void {
            if ($featured->media_url) {
                Storage::disk('public')->delete($featured->media_url);
            }
            if ($featured->thumbnail_url) {
                Storage::disk('public')->delete($featured->thumbnail_url);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
