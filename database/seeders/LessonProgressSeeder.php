<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonUserProgress;
use App\Models\User;
use Illuminate\Database\Seeder;

class LessonProgressSeeder extends Seeder
{
    /**
     * Seed some lesson progress so demo accounts show partial completion.
     */
    public function run(): void
    {
        $students = User::where('is_admin', false)->get();

        if ($students->isEmpty()) {
            $this->command->warn('No non-admin users found. Skipping lesson progress seeding.');

            return;
        }

        // Mark some lessons as completed for the first student
        $firstStudent = $students->first();
        $lessons = Lesson::all();

        if ($lessons->isEmpty()) {
            $this->command->warn('No lessons found. Run CourseSeeder first.');

            return;
        }

        // Complete first 3 lessons for the first student
        $completedCount = 0;
        foreach ($lessons->take(3) as $lesson) {
            $quiz = $lesson->quiz;
            if (! $quiz) {
                continue;
            }

            $questions = $quiz->questions;
            $answers = [];

            foreach ($questions as $qIdx => $question) {
                $correctIndex = collect($question['options'] ?? [])
                    ->search(fn ($opt) => ($opt['is_correct'] ?? false) === true);

                if ($correctIndex !== false) {
                    $answers[] = [
                        'questionIndex' => $qIdx,
                        'selectedOptionIndex' => $correctIndex,
                    ];
                }
            }

            LessonUserProgress::firstOrCreate(
                [
                    'user_id' => $firstStudent->id,
                    'lesson_id' => $lesson->id,
                ],
                [
                    'completed' => true,
                    'quiz_score' => 100,
                    'attempts' => 1,
                    'quiz_answers' => $answers,
                    'completed_at' => now()->subDays(rand(1, 7)),
                ]
            );

            $completedCount++;
        }

        // Update the pivot table for the first student
        $courses = Course::all();
        $lessonIdsByCourse = [];

        foreach ($lessons->take(3) as $lesson) {
            $module = $lesson->module;
            if ($module) {
                $courseId = $module->course_id;
                if (! isset($lessonIdsByCourse[$courseId])) {
                    $lessonIdsByCourse[$courseId] = [];
                }
                $lessonIdsByCourse[$courseId][] = $lesson->id;
            }
        }

        foreach ($lessonIdsByCourse as $courseId => $ids) {
            $firstStudent->courses()->syncWithoutDetaching([
                $courseId => [
                    'completed_lessons' => count($ids),
                    'xp_earned' => count($ids) * 10,
                ],
            ]);
        }

        // Mark a couple lessons for other students too
        if ($students->count() > 1) {
            $secondStudent = $students->skip(1)->first();
            $firstLesson = $lessons->first();

            if ($firstLesson && $secondStudent) {
                $quiz = $firstLesson->quiz;
                if ($quiz) {
                    LessonUserProgress::firstOrCreate(
                        [
                            'user_id' => $secondStudent->id,
                            'lesson_id' => $firstLesson->id,
                        ],
                        [
                            'completed' => true,
                            'quiz_score' => 100,
                            'attempts' => 1,
                            'quiz_answers' => [],
                            'completed_at' => now()->subDays(2),
                        ]
                    );
                }
            }
        }

        $this->command->info("Seeded lesson progress for {$completedCount} lessons.");
    }
}
