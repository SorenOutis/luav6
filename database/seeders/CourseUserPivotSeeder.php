<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Seeder;

class CourseUserPivotSeeder extends Seeder
{
    /**
     * Seed course-user enrollments (pivot table) for demo students.
     * This connects students to courses so they appear on their course catalog.
     */
    public function run(): void
    {
        $courses = Course::all();
        $students = User::where('is_admin', false)->get();

        if ($students->isEmpty()) {
            $this->command->warn('No non-admin users found. Skipping course enrollment seeding. Create a student user first.');

            return;
        }

        // Enroll the first student in all courses
        $firstStudent = $students->first();
        foreach ($courses as $course) {
            $firstStudent->courses()->syncWithoutDetaching([
                $course->id => [
                    'completed_lessons' => 0,
                    'xp_earned' => 0,
                ],
            ]);
        }

        // Enroll remaining students in a subset
        if ($students->count() > 1) {
            $courseIds = $courses->pluck('id')->toArray();

            foreach ($students->skip(1) as $index => $student) {
                // Each student gets courses based on their index (cycling through courses)
                $assignedCourseIds = [];
                for ($i = 0; $i < count($courseIds); $i++) {
                    if (($i + $index) % 2 === 0) {
                        $assignedCourseIds[] = $courseIds[$i];
                    }
                }

                foreach ($assignedCourseIds as $courseId) {
                    $student->courses()->syncWithoutDetaching([
                        $courseId => [
                            'completed_lessons' => rand(0, 2),
                            'xp_earned' => rand(0, 50),
                        ],
                    ]);
                }
            }
        }

        $this->command->info('Enrolled students in demo courses.');
    }
}
