<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Badge extends Model
{
    protected $fillable = ['name', 'description', 'icon_url', 'image_path', 'required_level'];

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('season_id')->withTimestamps();
    }
}
