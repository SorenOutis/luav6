<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonUserProgress;
use App\Models\Season;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CourseController extends Controller
{
    /**
     * Show the student's enrolled courses (catalog page).
     *
     * Phase 3.6 — Fixed N+1 queries:
     * - Batch-loaded completed lessons count (was 1 query per course)
     * - Removed empty whereHas('modules.lessons', ...) that silently filtered
     *   out courses with no lessons (live bug)
     * - Used withCount('modules') instead of modules()->count() per course
     * - Pre-loaded total_lessons via withCount on the modules relationship
     */
    public function index()
    {
        $user = auth()->user();
        $currentSeason = Season::current();

        $courses = $user->courses()
            ->when($currentSeason, function ($query) use ($currentSeason, $user) {
                // Scope by season if available
                $userSeasonIds = $user->sections()
                    ->wherePivot('season_id', $currentSeason->id)
                    ->pluck('sections.id');

                if ($userSeasonIds->isNotEmpty()) {
                    // Intentionally no additional filter — the enrollment pivot
                    // already scopes courses to the user's sections/season.
                }
            })
            ->withCount('modules')
            ->get()
            ->map(function ($course) {
                return [
                    'id' => $course->id,
                    'name' => $course->name,
                    'description' => $course->description,
                    'cover_photo' => $course->cover_photo_url,
                    'totalLessons' => $course->total_lessons,
                    'completedLessons' => $course->pivot->completed_lessons ?? 0,
                    'progress' => $course->total_lessons > 0
                        ? round((($course->pivot->completed_lessons ?? 0) / $course->total_lessons) * 100)
                        : 0,
                    'xpEarned' => $course->pivot->xp_earned ?? 0,
                    'modulesCount' => (int) $course->modules_count,
                ];
            });

        return Inertia::render('Courses/Index', [
            'courses' => $courses,
        ]);
    }

    /**
     * Show a single course with its modules and lessons.
     */
    public function show(Course $course)
    {
        $user = auth()->user();

        // Check enrollment
        if (! $user->is_admin && ! $user->courses()->where('course_id', $course->id)->exists()) {
            abort(403, 'You are not enrolled in this course.');
        }

        $course->load(['modules' => function ($query) {
            $query->orderBy('sort_order')->with(['lessons' => function ($query) {
                $query->orderBy('sort_order')->with('quiz');
            }]);
        }]);

        $totalLessons = $course->total_lessons;
        $completedLessons = $course->pivot->completed_lessons ?? 0;

        // Pre-load all user progress for this course's lessons (N+1 prevention)
        $lessonIds = $course->modules->pluck('lessons.*.id')->flatten();
        $allProgress = LessonUserProgress::where('user_id', $user->id)
            ->whereIn('lesson_id', $lessonIds)
            ->get()
            ->keyBy('lesson_id');

        // Build progress for each lesson
        $modules = $course->modules->map(function ($module) use ($allProgress) {
            return [
                'id' => $module->id,
                'title' => $module->title,
                'description' => $module->description,
                'sortOrder' => $module->sort_order,
                'lessons' => $module->lessons->map(function ($lesson) use ($allProgress) {
                    $progress = $allProgress->get($lesson->id);

                    return [
                        'id' => $lesson->id,
                        'title' => $lesson->title,
                        'sortOrder' => $lesson->sort_order,
                        'completed' => $progress?->completed ?? false,
                        'quizScore' => $progress?->quiz_score,
                        'hasQuiz' => $lesson->relationLoaded('quiz') && $lesson->quiz !== null,
                    ];
                })->values(),
            ];
        })->values();

        return Inertia::render('Courses/Show', [
            'course' => [
                'id' => $course->id,
                'name' => $course->name,
                'description' => $course->description,
                'cover_photo' => $course->cover_photo_url,
                'totalLessons' => $totalLessons,
                'completedLessons' => $completedLessons,
                'progress' => $totalLessons > 0
                    ? round(($completedLessons / $totalLessons) * 100)
                    : 0,
            ],
            'modules' => $modules,
        ]);
    }

    /**
     * Show a single lesson with content and quiz.
     */
    public function lesson(Course $course, Lesson $lesson)
    {
        $user = auth()->user();

        // Verify lesson belongs to this course
        if ($lesson->module->course_id !== $course->id) {
            abort(404);
        }

        // Check enrollment
        if (! $user->is_admin && ! $user->courses()->where('course_id', $course->id)->exists()) {
            abort(403, 'You are not enrolled in this course.');
        }

        $course->load(['modules' => function ($query) {
            $query->orderBy('sort_order')->with(['lessons' => function ($query) {
                $query->orderBy('sort_order')->with('quiz');
            }]);
        }]);

        $lesson->load('quiz');

        $totalLessons = $course->total_lessons;
        $completedLessons = $course->pivot->completed_lessons ?? 0;

        // Pre-load all user progress for this course's lessons (N+1 prevention)
        $lessonIds = $course->modules->pluck('lessons.*.id')->flatten();
        $allProgress = LessonUserProgress::where('user_id', $user->id)
            ->whereIn('lesson_id', $lessonIds)
            ->get()
            ->keyBy('lesson_id');

        // Build modules for sidebar
        $modules = $course->modules->map(function ($module) use ($allProgress) {
            return [
                'id' => $module->id,
                'title' => $module->title,
                'description' => $module->description,
                'sortOrder' => $module->sort_order,
                'lessons' => $module->lessons->map(function ($l) use ($allProgress) {
                    $progress = $allProgress->get($l->id);

                    return [
                        'id' => $l->id,
                        'title' => $l->title,
                        'sortOrder' => $l->sort_order,
                        'completed' => $progress?->completed ?? false,
                        'quizScore' => $progress?->quiz_score,
                        'hasQuiz' => $l->relationLoaded('quiz') && $l->quiz !== null,
                    ];
                })->values(),
            ];
        })->values();

        // Get user's progress for this specific lesson
        $userProgress = LessonUserProgress::where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->first();

        // Get quiz result from flash session (set by submitQuiz)
        $quizResult = session()->pull('quizResult');

        // Get previous and next lesson IDs for navigation
        $allLessonIds = $course->modules->pluck('lessons.*.id')->flatten()->values();
        $currentIndex = $allLessonIds->search($lesson->id);
        $prevLessonId = $currentIndex > 0 ? $allLessonIds[$currentIndex - 1] : null;
        $nextLessonId = $currentIndex < $allLessonIds->count() - 1 ? $allLessonIds[$currentIndex + 1] : null;

        return Inertia::render('Courses/Lesson', [
            'course' => [
                'id' => $course->id,
                'name' => $course->name,
            ],
            'lesson' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'content' => $lesson->content,
                'videoUrl' => $lesson->video_url,
                'mediaAttachments' => $lesson->media_attachments,
            ],
            // ⚠️ Quiz questions hold the answer key (options[].is_correct).
            // Only reveal it once the student has completed the lesson.
            'quiz' => $lesson->quiz ? [
                'id' => $lesson->quiz->id,
                'questions' => $this->serializeQuizQuestions(
                    $lesson->quiz->questions,
                    (bool) ($userProgress?->completed ?? false),
                ),
                'passScore' => $lesson->quiz->pass_score,
                'allowedAttempts' => $lesson->quiz->allowed_attempts,
            ] : null,
            'userProgress' => $userProgress ? [
                'completed' => $userProgress->completed,
                'quizScore' => $userProgress->quiz_score,
                'attempts' => $userProgress->attempts,
                'quizAnswers' => $userProgress->quiz_answers,
                'completedAt' => $userProgress->completed_at?->toIso8601String(),
            ] : [
                'completed' => false,
                'quizScore' => null,
                'attempts' => 0,
                'quizAnswers' => null,
                'completedAt' => null,
            ],
            'quizResult' => $quizResult,
            'prevLessonId' => $prevLessonId,
            'nextLessonId' => $nextLessonId,
            'modules' => $modules,
            'courseProgress' => [
                'totalLessons' => $totalLessons,
                'completedLessons' => $completedLessons,
                'progress' => $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100) : 0,
            ],
        ]);
    }

    /**
     * Strip the answer key from quiz questions unless the student has finished
     * the lesson (review mode).
     *
     * @param  mixed  $questions
     * @return array<int, array<string, mixed>>
     */
    private function serializeQuizQuestions($questions, bool $revealAnswers): array
    {
        if (! is_array($questions)) {
            return [];
        }

        return collect($questions)->map(function ($question) use ($revealAnswers) {
            $safe = [
                'text' => $question['text'] ?? '',
                'type' => $question['type'] ?? 'multiple_choice',
                'points' => $question['points'] ?? null,
            ];

            if (isset($question['options']) && is_array($question['options'])) {
                $safe['options'] = collect($question['options'])->map(function ($option) use ($revealAnswers) {
                    $opt = ['text' => $option['text'] ?? ''];

                    if ($revealAnswers) {
                        $opt['is_correct'] = (bool) ($option['is_correct'] ?? false);
                    }

                    return $opt;
                })->values()->all();
            }

            if ($revealAnswers && array_key_exists('correct_answer', $question)) {
                $safe['correct_answer'] = $question['correct_answer'];
            }

            return $safe;
        })->values()->all();
    }

    /**
     * Submit quiz answers for a lesson.
     */
    public function submitQuiz(Request $request, Course $course, Lesson $lesson)
    {
        $user = auth()->user();

        // Verify lesson belongs to this course
        if ($lesson->module->course_id !== $course->id) {
            abort(404);
        }

        // Check enrollment
        if (! $user->is_admin && ! $user->courses()->where('course_id', $course->id)->exists()) {
            abort(403, 'You are not enrolled in this course.');
        }

        $quiz = $lesson->quiz;
        if (! $quiz) {
            return redirect()->back()->withErrors(['quiz' => 'This lesson has no quiz.']);
        }

        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*.questionIndex' => 'required|integer|min:0',
            'answers.*.selectedOptionIndex' => 'required|integer|min:0',
        ]);

        // Calculate score
        $questions = $quiz->questions;
        $totalQuestions = count($questions);
        $correctAnswers = 0;

        $submittedAnswers = collect($validated['answers'])->keyBy('questionIndex');

        foreach ($questions as $index => $question) {
            $submitted = $submittedAnswers->get($index);
            if (! $submitted) {
                continue;
            }

            $correctOptionIndex = collect($question['options'] ?? [])
                ->search(fn ($opt) => ($opt['is_correct'] ?? false) === true);

            if ($correctOptionIndex !== false && (int) $submitted['selectedOptionIndex'] === (int) $correctOptionIndex) {
                $correctAnswers++;
            }
        }

        $score = $totalQuestions > 0
            ? (int) round(($correctAnswers / $totalQuestions) * 100)
            : 0;

        $passed = $score >= $quiz->pass_score;

        // Get or create progress record
        $progress = LessonUserProgress::where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->first();

        $attempts = ($progress?->attempts ?? 0) + 1;

        // Check if user has attempts remaining
        if ($quiz->allowed_attempts > 0 && $attempts > $quiz->allowed_attempts) {
            return redirect()->back()->withErrors(['quiz' => 'You have used all your allowed attempts for this quiz.']);
        }

        LessonUserProgress::updateOrCreate(
            [
                'user_id' => $user->id,
                'lesson_id' => $lesson->id,
            ],
            [
                'completed' => $passed,
                'quiz_score' => $score,
                'attempts' => $attempts,
                'quiz_answers' => $validated['answers'],
                'completed_at' => $passed ? now() : null,
            ]
        );

        // Update the pivot completed_lessons count
        if ($passed) {
            $completedCount = LessonUserProgress::where('user_id', $user->id)
                ->where('completed', true)
                ->whereIn('lesson_id', function ($query) use ($course) {
                    $query->select('id')
                        ->from('lessons')
                        ->whereIn('course_module_id', function ($q) use ($course) {
                            $q->select('id')->from('course_modules')->where('course_id', $course->id);
                        });
                })
                ->count();

            $user->courses()->updateExistingPivot($course->id, [
                'completed_lessons' => $completedCount,
            ]);
        }

        return redirect()->route('courses.lesson', [
            'course' => $course->id,
            'lesson' => $lesson->id,
        ])->with('quizResult', [
            'passed' => $passed,
            'score' => $score,
            'passScore' => $quiz->pass_score,
            'totalQuestions' => $totalQuestions,
            'correctAnswers' => $correctAnswers,
        ]);
    }
}
