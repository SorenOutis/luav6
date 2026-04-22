<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    /**
     * Get the users associated with the section.
     */
    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Get the exams associated with the section.
     */
    public function exams()
    {
        return $this->hasMany(Exam::class);
    }

    public function progress()
    {
        return $this->hasMany(SectionProgress::class);
    }
}
