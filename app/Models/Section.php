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
            'First Semester - 1st Quarter Grade' => 'First Semester - 1st Quarter Grade',
            'First Semester - 2nd Quarter Grade' => 'First Semester - 2nd Quarter Grade',
            'Second Semester - 1st Quarter Grade' => 'Second Semester - 1st Quarter Grade',
            'Second Semester - 2nd Quarter Grade' => 'Second Semester - 2nd Quarter Grade',
        ];
    }

    public static function seniorHighGradeSemesters(): array
    {
        return [
            [
                'key' => 'first_semester',
                'label' => 'First Semester',
                'quarters' => [
                    [
                        'key' => 'First Semester - 1st Quarter Grade',
                        'label' => '1st Quarter Grade',
                    ],
                    [
                        'key' => 'First Semester - 2nd Quarter Grade',
                        'label' => '2nd Quarter Grade',
                    ],
                ],
            ],
            [
                'key' => 'second_semester',
                'label' => 'Second Semester',
                'quarters' => [
                    [
                        'key' => 'Second Semester - 1st Quarter Grade',
                        'label' => '1st Quarter Grade',
                    ],
                    [
                        'key' => 'Second Semester - 2nd Quarter Grade',
                        'label' => '2nd Quarter Grade',
                    ],
                ],
            ],
        ];
    }
}
