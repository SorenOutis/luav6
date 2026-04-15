<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SectionProgress extends Model
{
    protected $table = 'section_progress';

    protected $fillable = ['user_id', 'section_id', 'exp', 'points', 'level'];

    protected static function booted()
    {
        static::saving(function (SectionProgress $progress) {
            // Level 1: 0-99 XP
            // Level 2: 100-199 XP
            $progress->level = floor($progress->exp / 100) + 1;
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }
}
