<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class Badge extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['name', 'description', 'icon_url', 'image_path', 'required_level', 'admin_id'];

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('season_id')->withTimestamps();
    }
}
