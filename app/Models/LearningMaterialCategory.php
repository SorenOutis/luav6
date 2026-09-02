<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LearningMaterialCategory extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'workspace_id',
        'admin_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (blank($model->slug) && filled($model->name)) {
                $model->slug = Str::slug($model->name);
            }
        });

        static::updating(function (self $model) {
            if ($model->isDirty('name') && blank($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    public function materials()
    {
        return $this->hasMany(LearningMaterial::class);
    }
}
