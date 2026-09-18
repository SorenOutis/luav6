<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Support\PublicFileUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class LearningMaterial extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'title',
        'description',
        'learning_material_category_id',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'file_extension',
        'cover_image',
        'status',
        'is_downloadable',
        'sort_order',
        'view_count',
        'download_count',
        'workspace_id',
        'admin_id',
    ];

    protected $casts = [
        'is_downloadable' => 'boolean',
        'sort_order' => 'integer',
        'file_size' => 'integer',
        'view_count' => 'integer',
        'download_count' => 'integer',
    ];

    protected $attributes = [
        'status' => 'draft',
        'is_downloadable' => true,
        'sort_order' => 0,
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PUBLISHED => 'Published',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(LearningMaterialCategory::class, 'learning_material_category_id');
    }

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'learning_material_section')->withTimestamps();
    }

    public function getFileUrlAttribute(): ?string
    {
        return PublicFileUrl::resolve($this->file_path);
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        return PublicFileUrl::resolve($this->cover_image);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeVisibleToStudents(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * @param  Collection<int, int>|array<int, int>  $sectionIds
     */
    public function scopeVisibleToSections(Builder $query, Collection|array $sectionIds): Builder
    {
        $sectionIds = collect($sectionIds)->filter()->unique()->values();

        if ($sectionIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->visibleToStudents()
            ->whereHas('sections', fn (Builder $q) => $q->whereIn('sections.id', $sectionIds));
    }

    public function isVisibleTo(User $user): bool
    {
        if ($user->is_admin) {
            return true;
        }

        if ($this->status !== self::STATUS_PUBLISHED) {
            return false;
        }

        return $this->sections()
            ->whereIn('sections.id', $user->sections()->pluck('sections.id'))
            ->exists();
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }
}
