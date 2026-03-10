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

        // 4. Leaderboard (Scoped to Current Season)
        if ($currentSeason) {
            $leaderboardUsers = \App\Models\SeasonProgress::with('user')
                ->where('season_id', $currentSeason->id)
                ->whereHas('user', fn($q) => $q->where('is_admin', false))
                ->orderByDesc('exp')
                ->get()
                ->map(function ($progress) use ($user) {
                    $u = $progress->user;
                    // Completion rate = lessons completed / total available in season?
// Let's just do a dummy calc for now based on user's courses
                    $totalLessons = $u->courses()->sum('total_lessons');
                    $completedLessons = $u->courses()->sum('completed_lessons');
                    $completionRate = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100) : 0;

                    // Weekly XP: xp earned in last 7 days from courses
                    $weeklyXp = $u->courses()
                        ->wherePivot('updated_at', '>=', now()->subDays(7))
                        ->sum('xp_earned');

                    return [
                        'id' => $u->id,
                        'name' => $u->name,
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
                ->where('exp', '>', $seasonalExp)
                ->count() + 1;
            $totalPlayers = \App\Models\SeasonProgress::where('season_id', $currentSeason->id)->count();
        } else {
            $leaderboardUsers = collect();
            $userRank = 0;
            $totalPlayers = 0;
        }

        return inertia('Dashboard', [
            'userStats' => [
                'totalXP' => $seasonalExp,
                'level' => $seasonalLevel,
                'currentXP' => $seasonalExp % 100,
                'maxXPForLevel' => 100,
                'rank' => 'Player',
                'rankNumber' => $userRank,
                'totalPlayers' => $totalPlayers,
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
            'leaderboardUsers' => $leaderboardUsers,
            'activeSeason' => $currentSeason ? [
                'id' => $currentSeason->id,
                'name' => $currentSeason->name,
            ] : null,
        ]);
    })->name('dashboard');

    Route::get('assignments', [\App\Http\Controllers\AssignmentController::class, 'index'])->name('assignments.index');
    Route::post('assignments/{assignment}/submit', [\App\Http\Controllers\AssignmentController::class, 'store'])->name('assignments.submit');

    Route::get('exams', [ExamController::class, 'index'])->name('exams.index');
    Route::get('exams/{exam}', [ExamController::class, 'show'])->name('exams.show');
    Route::post('exams/{exam}/parts/{examPart}/submit', [ExamController::class, 'submitPart'])->name('exams.submitPart');

    // Admin routes
    Route::get('admin/exams/submissions', [\App\Http\Controllers\Admin\ExamSubmissionController::class, 'index'])->name('admin.exams.submissions');
    Route::get('admin/exams/{exam}/submissions', [\App\Http\Controllers\Admin\ExamSubmissionController::class, 'examSubmissions'])->name('admin.exams.submissions.by-exam');
});

require __DIR__ . '/settings.php';