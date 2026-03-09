<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = ['name', 'total_lessons'];

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('completed_lessons', 'xp_earned', 'next_deadline')->withTimestamps();
    }
}
