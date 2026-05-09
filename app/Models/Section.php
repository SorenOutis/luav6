<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'school_level',
        'password',
    ];

    public const SCHOOL_LEVEL_COLLEGE = 'college';

    public const SCHOOL_LEVEL_SENIOR_HIGH = 'senior_high';

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

    public function grades()
    {
        return $this->hasMany(Grade::class);
    }

    public function gradePeriods(): array
    {
        if ($this->school_level === self::SCHOOL_LEVEL_SENIOR_HIGH) {
            return self::seniorHighGradePeriods();
        }

        return self::collegeGradePeriods();
    }

    public static function schoolLevelOptions(): array
    {
        return [
            self::SCHOOL_LEVEL_COLLEGE => 'College',
            self::SCHOOL_LEVEL_SENIOR_HIGH => 'Senior High School',
        ];
    }

    public static function collegeGradePeriods(): array
    {
        return [
            'Prelim' => 'Prelim',
            'Midterm' => 'Midterm',
            'Final' => 'Final',
        ];
    }

    public static function seniorHighGradePeriods(): array
    {
        return [
            '1st Quarter Grade' => '1st Quarter Grade',
            '2nd Quarter Grade' => '2nd Quarter Grade',
            '3rd Quarter Grade' => '3rd Quarter Grade',
            '4th Quarter Grade' => '4th Quarter Grade',
        ];
    }
}
