<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Section extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'name',
        'season_id',
        'school_level',
        'join_code',
        'admin_id',
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
     * Generate a unique 8-character join code (format: XXXXXXXX).
     * Uses uppercase alphanumeric characters, excluding ambiguous ones (O,0,I,1).
     *
     * Join codes must be globally unique across ALL admins' sections,
     * so we bypass the workspace global scope here.
     */
    public static function generateUniqueJoinCode(): string
    {
        $characters = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $maxAttempts = 100;
        $attempts = 0;

        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
            $attempts++;
        } while (static::withoutGlobalScope('workspace')->where('join_code', $code)->exists() && $attempts < $maxAttempts);

        return $code;
    }

    /**
     * Format a join code with a hyphen in the middle for display (e.g., 9H84K6B5 → 9H84-K6B5).
     */
    public static function formatJoinCode(string $code): string
    {
        return substr($code, 0, 4).'-'.substr($code, 4);
    }

    /**
     * Normalize a join code by removing hyphens and uppercasing.
     */
    public static function normalizeJoinCode(string $code): string
    {
        return Str::upper(str_replace('-', '', $code));
    }

    /**
     * Find a section by join code across ALL workspaces.
     * Join codes are globally unique, so we bypass the workspace scope here.
     */
    public static function findByJoinCode(string $code): ?self
    {
        return static::withoutGlobalScope('workspace')
            ->where('join_code', $code)
            ->first();
    }

    /**
     * Get the users associated with the section.
     */
    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('season_id');
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

    public function season()
    {
        return $this->belongsTo(Season::class);
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
