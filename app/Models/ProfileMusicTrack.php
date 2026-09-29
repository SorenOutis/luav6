<?php

namespace App\Models;

use App\Support\PublicFileUrl;
use App\Support\WorkspaceContext;
use Database\Factories\ProfileMusicTrackFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProfileMusicTrack extends Model
{
    /** @use HasFactory<ProfileMusicTrackFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (ProfileMusicTrack $track): void {
            if (is_null($track->admin_id) && auth()->id()) {
                $track->admin_id = auth()->id();
            }
            if (is_null($track->workspace_id)) {
                $track->workspace_id = app(WorkspaceContext::class)->id() ?? auth()->user()?->current_workspace_id;
            }
        });
    }

    protected $fillable = [
        'workspace_id',
        'admin_id',
        'title',
        'artist',
        'audio_path',
        'duration_seconds',
        'cover_image_path',
        'source_url',
        'license_name',
        'attribution_text',
        'is_active',
        'is_global',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_seconds' => 'float',
            'is_active' => 'boolean',
            'is_global' => 'boolean',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'profile_music_track_id');
    }

    public function scopeAvailableForWorkspace(Builder $query, ?int $workspaceId): Builder
    {
        return $query->where(function (Builder $builder) use ($workspaceId): void {
            $builder->where('is_global', true)
                ->orWhereNull('workspace_id');

            if ($workspaceId) {
                $builder->orWhere('workspace_id', $workspaceId);
            }
        });
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function audioUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => PublicFileUrl::resolve($this->audio_path),
        );
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function coverImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => PublicFileUrl::resolve($this->cover_image_path),
        );
    }
}
