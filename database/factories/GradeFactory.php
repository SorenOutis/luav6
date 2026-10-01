<?php

namespace Database\Factories;

use App\Models\Grade;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grade>
 */
class GradeFactory extends Factory
{
    protected $model = Grade::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'section_id' => Section::factory(),
            'subject' => 'Mathematics',
            'period' => '1st Quarter',
            'score' => 85.0,
            'max_score' => 100.0,
            'remarks' => null,
            'recorded_by' => null,
            'workspace_id' => null,
        ];
    }
}
