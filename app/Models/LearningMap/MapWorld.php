<?php

namespace App\Models\LearningMap;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MapWorld extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'slug', 'name', 'sort_order',
        'primary_color', 'accent_color', 'background_class',
        'admin_id',
    ];

    public function nodes(): HasMany
    {
        return $this->hasMany(MapNode::class)->orderBy('id');
    }
}
