<?php

namespace App\Models\LearningMap;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MapWorld extends Model
{
    protected $fillable = [
        'slug', 'name', 'sort_order',
        'primary_color', 'accent_color', 'background_class',
    ];

    public function nodes(): HasMany
    {
        return $this->hasMany(MapNode::class)->orderBy('id');
    }
}
