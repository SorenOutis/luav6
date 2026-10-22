<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class CommunityStory extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'section_id',
        'user_id',
        'media_url',
        'media_type',
        'duration_seconds',
        'caption',
        'views_count',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'views_count' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (CommunityStory $story): void {
            if ($story->media_url) {
                Storage::disk('public')->delete($story->media_url);
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

    public function views(): HasMany
    {
        return $this->hasMany(CommunityStoryView::class, 'story_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(CommunityStoryReaction::class, 'story_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }
}
