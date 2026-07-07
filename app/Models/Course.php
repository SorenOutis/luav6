<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['name', 'total_lessons', 'admin_id'];

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('completed_lessons', 'xp_earned', 'next_deadline')->withTimestamps();
    }
}
