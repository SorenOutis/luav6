<?php

use App\Http\Controllers\Admin\ExamSubmissionController;
use App\Http\Controllers\AnonymousMessageController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\PublicProfileController;
use App\Http\Controllers\Settings\ProfileController;
use App\Models\Announcement;
use App\Models\Exam;
use App\Models\ExamSubmission;
use App\Models\Season;
use App\Models\Section;
use App\Models\SectionProgress;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
    'totalUsers' => User::count(),
])->name('home');

Route::middleware(['auth', 'verified', 'banned.redirect'])->group(function () {
    Route::get('dashboard', function () {
        $user = auth()->user();
        $currentSeason = Season::current();

        // --- Streak Logic ---
        $now = now();
        if (! $user->last_login_at) {
            $user->update(['current_streak' => 1, 'last_login_at' => $now]);
        } elseif (! $user->last_login_at->isToday()) {
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
        $announcements = Announcement::where('is_active', true)->get();

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
                'isOverdue' => $assignment->due_date ? Carbon::parse($assignment->due_date)->isPast() : false,
                'submitted' => (bool) $assignment->pivot->submitted,
                'status' => $assignment->pivot->status,
                'grade' => $assignment->pivot->grade,
            ];
        });

        // 5. Upcoming Exams (Published exams, ordered by date)
        $upcomingExams = Exam::where('status', '!=', 'draft')
            ->when(! $user->is_admin, function ($query) use ($sectionIds) {
                $query->where(function ($query) use ($sectionIds) {
                    $query->whereNull('section_id')
                        ->orWhereIn('section_id', $sectionIds);
                });
            })
            ->orderBy('exam_date', 'asc')
            ->limit(3)
            ->get()
            ->map(function ($exam) use ($user) {
                $submittedPartsCount = ExamSubmission::where('user_id', $user->id)
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
                // Get all students in this section with their section-specific progress
                $usersInSection = $section->users()
                    ->where('is_admin', false)
                    ->with(['sectionProgress' => function ($q) use ($section) {
                        $q->where('section_id', $section->id);
                    }])
                    ->get();

                $userIds = $usersInSection->pluck('id')->unique();

                // Get current user's progress in this section for ranking
                $currentUserSectionProgress = $user->activeSectionProgress($section->id);
                $sectionExp = $currentUserSectionProgress?->exp ?? 0;

                // Get weekly XP for all users in one query
                $weeklyXpMap = DB::table('course_user')
                    ->whereIn('user_id', $userIds)
                    ->where('updated_at', '>=', now()->subDays(7))
                    ->select('user_id', DB::raw('SUM(xp_earned) as total'))
                    ->groupBy('user_id')
                    ->pluck('total', 'user_id');

                $leaderboardUsers = $usersInSection->map(function ($u) use ($weeklyXpMap) {
                    $progress = $u->sectionProgress->first();
                    $xp = $progress?->exp ?? 0;
                    $level = $progress?->level ?? 1;

                    $xpProgress = (int) ($xp % 100); // 100 XP per level
                    $weeklyXp = $weeklyXpMap[$u->id] ?? 0;

                    return [
                        'id' => $u->id,
                        'name' => $u->name,
                        'avatar' => $u->avatar,
                        'xp' => (float) $xp,
                        'level' => (int) $level,
                        'xpProgress' => $xpProgress,
                        'streak' => $u->current_streak,
                        'joinedAt' => $u->created_at->format('M Y'),
                        'weeklyXp' => $weeklyXp,
                        'trend' => 'stable',
                        'isCurrentUser' => $u->id === auth()->id(),
                    ];
                })->sortByDesc('xp')->values();

                $userRank = SectionProgress::where('section_id', $section->id)
                    ->whereHas('user', function ($q) {
                        $q->where('is_admin', false);
                    })
                    ->where('exp', '>', $sectionExp)
                    ->count() + 1;

                $totalPlayers = $usersInSection->count();

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
                'achievements' => $user->badges()->when($currentSeason, fn ($q) => $q->wherePivot(
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
            'allSections' => Section::all(['id', 'name']),
        ]);
    })->name('dashboard');

    Route::get('u/{user}', [PublicProfileController::class, 'show'])->name('users.show');
    Route::patch('profile/section', [ProfileController::class, 'updateSection'])->name('profile.section.update');

    Route::get('assignments', [AssignmentController::class, 'index'])->name('assignments.index');
    Route::post('assignments/{assignment}/submit', [AssignmentController::class, 'store'])->name('assignments.submit');

    Route::get('exams', [ExamController::class, 'index'])->name('exams.index');
    Route::get('exams/{exam}', [ExamController::class, 'show'])->name('exams.show');
    Route::post('exams/{exam}/parts/{examPart}/submit', [ExamController::class, 'submitPart'])->name('exams.submitPart')->middleware('throttle:10,1');

    Route::get('ngl', [AnonymousMessageController::class, 'index'])->name('ngl.index');
    Route::post('ngl', [AnonymousMessageController::class, 'store'])->name('ngl.store');
    Route::post('ngl/{message}/like', [AnonymousMessageController::class, 'like'])->name('ngl.like');

    Route::post('api/chat', ChatController::class)->name('chat');
    Route::get('api/chat/history', [ChatController::class, 'getHistory'])->name('chat.history');

    // Admin routes
    Route::get('admin/exams/submissions', [ExamSubmissionController::class, 'index'])->name('admin.exams.submissions');
    Route::get('admin/exams/{exam}/submissions', [ExamSubmissionController::class, 'examSubmissions'])->name('admin.exams.submissions.by-exam');
});

require __DIR__.'/settings.php';
