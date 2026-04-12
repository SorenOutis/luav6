<?php

use App\Http\Controllers\ExamController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $user = auth()->user();
        $currentSeason = \App\Models\Season::current();

        // --- Streak Logic ---
        $now = now();
        if (!$user->last_login_at) {
            $user->update(['current_streak' => 1, 'last_login_at' => $now]);
        } elseif (!$user->last_login_at->isToday()) {
            if ($user->last_login_at->isYesterday()) {
                $user->increment('current_streak');
            } else {
                $user->update(['current_streak' => 1]);
            }
            $user->update(['last_login_at' => $now]);
        }

        // --- Seasonal Progress ---
        $seasonalProgress = $user->activeSeasonProgress();
        $seasonalExp = $seasonalProgress?->exp ?? 0;
        $seasonalLevel = $seasonalProgress?->level ?? 1;
        $seasonalPoints = $seasonalProgress?->points ?? 0;

        $sectionIds = $user->sections()->pluck('sections.id');

        // 1. Announcements (Active)
        $announcements = \App\Models\Announcement::where('is_active', true)->get();

        // 2. Courses (Enrolled by user, scoped to season if id exists)
        $coursesResource = $user->courses();
        if ($currentSeason) {
            $coursesResource->wherePivot('season_id', $currentSeason->id);
        }
        $courses = $coursesResource->get()->map(function ($course) {
            return [
                'id' => $course->id,
                'name' => $course->name,
                'progress' => $course->total_lessons > 0 ? round(($course->pivot->completed_lessons / $course->total_lessons) * 100) :
                    0,
                'completedLessons' => $course->pivot->completed_lessons,
                'totalLessons' => $course->total_lessons,
                'xpEarned' => $course->pivot->xp_earned,
                'nextDeadline' => $course->pivot->next_deadline ?? 'To be announced',
            ];
        });

        // 3. Assignments (Assigned to user)
        $assignments = $user->assignments()->get()->map(function ($assignment) {
            return [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'description' => $assignment->description,
                'dueDate' => $assignment->due_date ?? 'No deadline',
                'isOverdue' => $assignment->due_date ? \Carbon\Carbon::parse($assignment->due_date)->isPast() : false,
                'submitted' => (bool) $assignment->pivot->submitted,
                'status' => $assignment->pivot->status,
                'grade' => $assignment->pivot->grade,
            ];
        });

        // 5. Upcoming Exams (Published exams, ordered by date)
        $upcomingExams = \App\Models\Exam::where('status', '!=', 'draft')
            ->when(!$user->is_admin, function ($query) use ($sectionIds) {
                $query->where(function ($query) use ($sectionIds) {
                    $query->whereNull('section_id')
                          ->orWhereIn('section_id', $sectionIds);
                });
            })
            ->orderBy('exam_date', 'asc')
            ->limit(3)
            ->get()
            ->map(function ($exam) use ($user) {
                $submittedPartsCount = \App\Models\ExamSubmission::where('user_id', $user->id)
                    ->where('exam_id', $exam->id)
                    ->distinct('exam_part_id')
                    ->count();
                
                $totalParts = $exam->parts()->count();
                
                return [
                    'id' => $exam->id,
                    'title' => $exam->title,
                    'description' => $exam->description,
                    'exam_date' => $exam->exam_date->format('M d, Y'),
                    'duration_minutes' => $exam->duration_minutes,
                    'status' => $exam->status,
                    'parts_count' => $totalParts,
                    'submitted_parts' => $submittedPartsCount,
                    'is_completed' => $submittedPartsCount === $totalParts && $totalParts > 0,
                ];
            });
        

        // 4. Leaderboard (Scoped to Current Season and Section)
        $sectionLeaderboards = [];
        if ($currentSeason) {
            $userSections = $user->sections()->get();
            
            foreach ($userSections as $section) {
                $leaderboardUsers = \App\Models\SeasonProgress::with('user')
                    ->where('season_id', $currentSeason->id)
                    ->whereHas('user', function($q) use ($section) {
                        $q->where('is_admin', false)
                          ->whereHas('sections', function($q) use ($section) {
                              $q->where('sections.id', $section->id);
                          });
                    })
                    ->orderByDesc('exp')
                    ->get()
                    ->map(function ($progress) use ($user) {
                        $u = $progress->user;
                        $totalLessons = $u->courses()->sum('total_lessons');
                        $completedLessons = $u->courses()->sum('completed_lessons');
                        $completionRate = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100) : 0;
                        $weeklyXp = $u->courses()
                            ->wherePivot('updated_at', '>=', now()->subDays(7))
                            ->sum('xp_earned');

                        return [
                            'id' => $u->id,
                            'name' => $u->name,
                            'avatar' => $u->avatar,
                            'xp' => $progress->exp,
                            'level' => $progress->level,
                            'completionRate' => $completionRate,
                            'streak' => $u->current_streak,
                            'joinedAt' => $u->created_at->format('M Y'),
                            'weeklyXp' => $weeklyXp,
                            'trend' => 'stable',
                            'isCurrentUser' => $u->id === $user->id,
                        ];
                    });

                $userRank = \App\Models\SeasonProgress::where('season_id', $currentSeason->id)
                    ->whereHas('user', function($q) use ($section) {
                        $q->whereHas('sections', function($q) use ($section) {
                            $q->where('sections.id', $section->id);
                        });
                    })
                    ->where('exp', '>', $seasonalExp)
                    ->count() + 1;
                
                $totalPlayers = \App\Models\SeasonProgress::where('season_id', $currentSeason->id)
                    ->whereHas('user', function($q) use ($section) {
                        $q->whereHas('sections', function($q) use ($section) {
                            $q->where('sections.id', $section->id);
                        });
                    })
                    ->count();

                $sectionLeaderboards[] = [
                    'sectionId' => $section->id,
                    'sectionName' => $section->name,
                    'users' => $leaderboardUsers,
                    'userRank' => $userRank,
                    'totalPlayers' => $totalPlayers,
                ];
            }
        }

        // If no sections, we can provide a default empty state or global if desired
        // For now, if no sections, the list will just be empty.

        return inertia('Dashboard', [
            'userStats' => [
                'totalXP' => $seasonalExp,
                'level' => $seasonalLevel,
                'currentXP' => $seasonalExp % 100,
                'maxXPForLevel' => 100,
                'rank' => 'Player',
                'rankNumber' => count($sectionLeaderboards) > 0 ? $sectionLeaderboards[0]['userRank'] : 0,
                'totalPlayers' => count($sectionLeaderboards) > 0 ? $sectionLeaderboards[0]['totalPlayers'] : 0,
                'achievements' => $user->badges()->when($currentSeason, fn($q) => $q->wherePivot(
                    'season_id',
                    $currentSeason->id
                ))->count(),
                'points' => $seasonalPoints,
                'streak' => $user->current_streak,
                'joinedAt' => $user->created_at->format('M Y'),
            ],
            'announcements' => $announcements,
            'courses' => $courses,
            'assignments' => $assignments,
            'upcomingExams' => $upcomingExams,
            'sectionLeaderboards' => $sectionLeaderboards,
            'activeSeason' => $currentSeason ? [
                'id' => $currentSeason->id,
                'name' => $currentSeason->name,
            ] : null,
            'sectionName' => $user->sections->pluck('name')->join(', '),
            'allSections' => \App\Models\Section::all(['id', 'name']),
        ]);
    })->name('dashboard');

    Route::get('u/{user}', [\App\Http\Controllers\PublicProfileController::class, 'show'])->name('users.show');
    Route::patch('profile/section', [\App\Http\Controllers\Settings\ProfileController::class, 'updateSection'])->name('profile.section.update');

    Route::get('assignments', [\App\Http\Controllers\AssignmentController::class, 'index'])->name('assignments.index');
    Route::post('assignments/{assignment}/submit', [\App\Http\Controllers\AssignmentController::class, 'store'])->name('assignments.submit');

    Route::get('exams', [ExamController::class, 'index'])->name('exams.index');
    Route::get('exams/{exam}', [ExamController::class, 'show'])->name('exams.show');
    Route::post('exams/{exam}/parts/{examPart}/submit', [ExamController::class, 'submitPart'])->name('exams.submitPart')->middleware('throttle:10,1');

    // Admin routes
    Route::get('admin/exams/submissions', [\App\Http\Controllers\Admin\ExamSubmissionController::class, 'index'])->name('admin.exams.submissions');
    Route::get('admin/exams/{exam}/submissions', [\App\Http\Controllers\Admin\ExamSubmissionController::class, 'examSubmissions'])->name('admin.exams.submissions.by-exam');
});

require __DIR__ . '/settings.php';