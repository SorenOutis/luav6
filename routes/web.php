<?php

use App\Http\Controllers\Admin\ExamSubmissionController;
use App\Http\Controllers\AnonymousMessageController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\Games\GamesController;
use App\Http\Controllers\Games\TowerDefenseController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\LearningMapController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PublicProfileController;
use App\Http\Controllers\Settings\ProfileController;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Exam;
use App\Models\ExamSubmission;
use App\Models\Season;
use App\Models\Section;
use App\Models\SectionProgress;
use App\Models\Setting;
use App\Models\User;
use App\Services\BadgeAwardService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::get('/', function () {
    $currentSeason = Season::current();
    $demoVideoPath = Setting::get('welcome_demo_video_path');

    return inertia('Welcome', [
        'canRegister' => Features::enabled(Features::registration()) && (bool) Setting::get('registration_enabled', true),
        'totalUsers' => User::count(),
        'totalExams' => Exam::where('status', '!=', 'draft')->count(),
        'totalAssignments' => Assignment::count(),
        'totalSubmissions' => ExamSubmission::query()
            ->selectRaw('COUNT(*) as cnt')
            ->fromSub(
                ExamSubmission::select('user_id', 'exam_id')->distinct(),
                'sub'
            )->value('cnt'),
        'activeSeason' => $currentSeason ? [
            'name' => $currentSeason->name,
            'startDate' => $currentSeason->start_date?->toISOString(),
            'endDate' => $currentSeason->end_date?->toISOString(),
            'showCountdown' => (bool) $currentSeason->show_countdown_on_welcome,
        ] : null,
        'demoVideoUrl' => filled($demoVideoPath) ? asset('storage/'.$demoVideoPath) : null,
    ]);
})->name('home');

Route::get('/about', function () {
    return inertia('About', [
        'canRegister' => Features::enabled(Features::registration()) && (bool) Setting::get('registration_enabled', true),
        'totalUsers' => User::count(),
        'totalExams' => Exam::where('status', '!=', 'draft')->count(),
        'totalSubmissions' => ExamSubmission::count(),
    ]);
})->name('about');

Route::get('/how-it-works', function () {
    return inertia('HowItWorks', [
        'canRegister' => Features::enabled(Features::registration()) && (bool) Setting::get('registration_enabled', true),
    ]);
})->name('how-it-works');

Route::middleware(['auth', 'verified', 'banned.redirect'])->group(function () {
    Route::get('grades', [GradeController::class, 'index'])
        ->middleware('student.page:grades')
        ->name('grades');
    Route::get('api/grades', [GradeController::class, 'apiIndex'])
        ->middleware('student.page:grades')
        ->name('api.grades');

    Route::get('dashboard', function () {
        $user = auth()->user();
        $currentSeason = Season::current();

        // --- Streak Logic ---
        $now = now();
        if (! $user->last_login_at) {
            $user->update([
                'current_streak' => 1,
                'longest_streak' => max(1, (int) ($user->longest_streak ?? 0)),
                'last_login_at' => $now,
            ]);
        } elseif (! $user->last_login_at->isToday()) {
            if ($user->last_login_at->isYesterday()) {
                $user->increment('current_streak');
            } else {
                $user->update(['current_streak' => 1]);
            }
            $user->update(['last_login_at' => $now]);

            // Keep longest_streak in sync whenever current streak advances
            if (($user->current_streak ?? 0) > ($user->longest_streak ?? 0)) {
                $user->update(['longest_streak' => $user->current_streak]);
            }
        }

        // --- Activity / Login Dates for Heatmap (last 90 days) ---
        $loginDates = DB::table('gamification_histories')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(90))
            ->selectRaw('DATE(created_at) as d')
            ->distinct()
            ->pluck('d')
            ->map(fn ($d) => (string) $d)
            ->values();

        // Include today if the user has an up-to-date last_login_at
        if ($user->last_login_at && $user->last_login_at->isToday()) {
            $today = $now->toDateString();
            if (! $loginDates->contains($today)) {
                $loginDates->push($today);
            }
        }

        // --- Seasonal Progress ---
        $seasonalProgress = $user->activeSeasonProgress();
        $seasonalExp = $seasonalProgress?->exp ?? 0;
        $seasonalLevel = $seasonalProgress?->level ?? 1;
        $seasonalPoints = $seasonalProgress?->points ?? 0;

        app(BadgeAwardService::class)->awardEligibleBadges(
            $user,
            (int) $seasonalLevel,
            $currentSeason?->id
        );

        $earnedBadges = $user->badges()
            ->orderByPivot('created_at', 'desc')
            ->get();

        $badgeSeasonNames = Season::query()
            ->whereIn('id', $earnedBadges->pluck('pivot.season_id')->filter()->unique())
            ->pluck('name', 'id');

        $availableSeasonModels = Season::query()
            ->whereIn('id', DB::table('section_user')
                ->where('user_id', $user->id)
                ->whereNotNull('season_id')
                ->distinct()
                ->pluck('season_id')
            )
            ->orderBy('start_date', 'desc')
            ->get();

        $availableSeasons = $availableSeasonModels
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])
            ->values()
            ->all();

        // Use the user's first available season as the initial season for leaderboard data,
        // so the initial render matches what the dropdown shows (instead of showing ALL sections).
        $initialSeason = $availableSeasonModels->first() ?? $currentSeason;

        // Scope sectionIds to the user's initial season for exams and other scoped queries.
        // Fall back to all sections if no season exists (edge case during testing/setup).
        $sectionIds = $initialSeason
            ? $user->sections()->wherePivot('season_id', $initialSeason->id)->pluck('sections.id')
            : $user->sections()->pluck('sections.id');

        // 1. Announcements (Active)
        $announcements = Announcement::where('is_active', true)->get();

        // 2. Courses (Enrolled by user, scoped to season if id exists)
        $coursesResource = $user->courses();
        if ($initialSeason) {
            $coursesResource->wherePivot('season_id', $initialSeason->id);
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
            $due = $assignment->due_date ? Carbon::parse($assignment->due_date) : null;

            return [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'description' => $assignment->description,
                'dueDate' => $due ? $due->format('M d, Y') : 'No deadline',
                'dueAtIso' => $due?->toIso8601String(),
                'isOverdue' => $due ? $due->isPast() : false,
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
                    'exam_date_iso' => $exam->exam_date->toIso8601String(),
                    'duration_minutes' => $exam->duration_minutes,
                    'status' => $exam->status,
                    'parts_count' => $totalParts,
                    'submitted_parts' => $submittedPartsCount,
                    'is_completed' => $submittedPartsCount === $totalParts && $totalParts > 0,
                ];
            });

        // 4. Leaderboard (Scoped to initial season)
        $sectionLeaderboards = [];
        if ($initialSeason) {
            // Scope sections to the user's initial season via pivot — same as the API does
            $userSections = $user->sections()->wherePivot('season_id', $initialSeason->id)->get();

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
                        'blurred' => $u->blur_leaderboard,
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

        return inertia('Dashboard', [
            'userStats' => [
                'totalXP' => $seasonalExp,
                'level' => $seasonalLevel,
                'currentXP' => $seasonalExp % 100,
                'maxXPForLevel' => 100,
                'rank' => 'Player',
                'rankNumber' => count($sectionLeaderboards) > 0 ? $sectionLeaderboards[0]['userRank'] : 0,
                'totalPlayers' => count($sectionLeaderboards) > 0 ? $sectionLeaderboards[0]['totalPlayers'] : 0,
                'achievements' => $earnedBadges->count(),
                'points' => $seasonalPoints,
                'streak' => $user->current_streak,
                'longestStreak' => (int) ($user->longest_streak ?? 0),
                'joinedAt' => $user->created_at->format('M Y'),
            ],
            'userBadges' => $earnedBadges->map(fn ($badge) => [
                'id' => $badge->id,
                'name' => $badge->name,
                'description' => $badge->description,
                'requiredLevel' => $badge->required_level,
                'image' => $badge->image_path ? asset('storage/'.$badge->image_path) : null,
                'iconUrl' => $badge->icon_url,
                'earnedSeason' => $badge->pivot->season_id ? ($badgeSeasonNames[$badge->pivot->season_id] ?? 'Unknown Season') : null,
                'earnedAt' => optional($badge->pivot->created_at)?->format('M d, Y'),
            ])->values(),
            'loginDates' => $loginDates,
            'announcements' => $announcements,
            'courses' => $courses,
            'assignments' => $assignments,
            'upcomingExams' => $upcomingExams,
            'sectionLeaderboards' => $sectionLeaderboards,
            'activeSeason' => $currentSeason ? [
                'id' => $currentSeason->id,
                'name' => $currentSeason->name,
                'startDate' => $currentSeason->start_date?->toIso8601String(),
                'endDate' => $currentSeason->end_date?->toIso8601String(),
            ] : null,
            'sectionName' => $user->sections->pluck('name')->join(', '),
            'availableSeasons' => $availableSeasons,
        ]);
    })->middleware('student.page:dashboard')->name('dashboard');

    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');

    Route::get('u/{user}', [PublicProfileController::class, 'show'])->name('users.show');
    Route::get('users/{user}/xp-history', function (User $user) {
        return $user->gamificationHistories()
            ->with('section:id,name')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($history) {
                return [
                    'id' => $history->id,
                    'amount_xp' => (float) $history->amount_xp,
                    'reason' => $history->reason,
                    'description' => $history->description,
                    'section_name' => $history->section?->name,
                    'created_at' => $history->created_at->format('M d, Y H:i'),
                ];
            });
    })->name('users.xp-history');
    Route::patch('profile/section', [ProfileController::class, 'updateSection'])->name('profile.section.update');
    Route::post('sections/join-by-code', [ProfileController::class, 'joinByCode'])->name('sections.join-by-code');
    Route::post('sections/{section}/verify-password', [ProfileController::class, 'verifySectionPassword'])->name('sections.verify-password');

    Route::post('api/leaderboard/toggle-blur', function () {
        $user = auth()->user();
        $user->update([
            'blur_leaderboard' => ! $user->blur_leaderboard,
        ]);

        return response()->json([
            'blur_leaderboard' => $user->fresh()->blur_leaderboard,
        ]);
    })->middleware(['auth', 'verified'])->name('api.leaderboard.toggle-blur');

    Route::get('assignments', [AssignmentController::class, 'index'])->middleware('student.page:assignments')->name('assignments.index');
    Route::post('assignments/{assignment}/submit', [AssignmentController::class, 'store'])->middleware('student.page:assignments')->name('assignments.submit');

    Route::get('exams', [ExamController::class, 'index'])->middleware('student.page:exams')->name('exams.index');
    Route::get('exams/{exam}', [ExamController::class, 'show'])->middleware('student.page:exams')->name('exams.show');
    Route::post('exams/pre-warm-ai', [ExamController::class, 'preWarmAI'])->middleware('student.page:exams')->name('exams.preWarmAI');
    Route::post('exams/{exam}/monitor-progress', [ExamController::class, 'monitorProgress'])->middleware(['student.page:exams', 'throttle:60,1'])->name('exams.monitorProgress');
    // ℹ️ Scope binding via explicit controller check rather than
    // ->scopeBindings(), because the route parameter `{examPart}` does not
    // match the relationship name (`parts`) and Laravel's automatic scoping
    // would call the non-existent method Exam::examParts().
    Route::post('exams/{exam}/parts/{examPart}/start', [ExamController::class, 'startPart'])
        ->middleware(['student.page:exams', 'throttle:60,1'])
        ->name('exams.startPart');
    Route::post('exams/{exam}/parts/{examPart}/submit', [ExamController::class, 'submitPart'])
        ->middleware(['student.page:exams', 'throttle:10,1'])
        ->name('exams.submitPart');
    Route::get('exams/{exam}/parts/{examPart}/status', [ExamController::class, 'partStatus'])
        ->middleware(['student.page:exams', 'throttle:120,1'])
        ->name('exams.partStatus');

    Route::get('ngl', [AnonymousMessageController::class, 'index'])->middleware('student.page:ngl')->name('ngl.index');
    Route::post('ngl', [AnonymousMessageController::class, 'store'])->middleware('student.page:ngl')->name('ngl.store');
    Route::post('ngl/{message}/like', [AnonymousMessageController::class, 'like'])->middleware('student.page:ngl')->name('ngl.like');

    // ─────────────────────────────────────────────
    // Leaderboard API (season-scoped)
    // ─────────────────────────────────────────────
    Route::get('api/leaderboard', function () {
        $user = auth()->user();
        $seasonId = request()->integer('season_id');
        $season = $seasonId ? Season::find($seasonId) : Season::current();

        if (! $season) {
            return response()->json(['leaderboards' => [], 'selectedSeason' => null]);
        }

        // Get user's sections scoped to this season via pivot season_id
        $userSections = $user->sections()->wherePivot('season_id', $season->id)->get();

        $sectionLeaderboards = [];
        foreach ($userSections as $section) {
            $usersInSection = $section->users()
                ->where('is_admin', false)
                ->with(['sectionProgress' => function ($q) use ($section) {
                    $q->where('section_id', $section->id);
                }])
                ->get();

            $userIds = $usersInSection->pluck('id')->unique();

            $currentUserSectionProgress = $user->activeSectionProgress($section->id);
            $sectionExp = $currentUserSectionProgress?->exp ?? 0;

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

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'avatar' => $u->avatar,
                    'xp' => (float) $xp,
                    'level' => (int) $level,
                    'xpProgress' => (int) ($xp % 100),
                    'streak' => $u->current_streak,
                    'joinedAt' => $u->created_at->format('M Y'),
                    'weeklyXp' => (int) ($weeklyXpMap[$u->id] ?? 0),
                    'trend' => 'stable',
                    'isCurrentUser' => $u->id === auth()->id(),
                    'blurred' => $u->blur_leaderboard,
                ];
            })->sortByDesc('xp')->values();

            $userRank = SectionProgress::where('section_id', $section->id)
                ->whereHas('user', fn ($q) => $q->where('is_admin', false))
                ->where('exp', '>', $sectionExp)
                ->count() + 1;

            $sectionLeaderboards[] = [
                'sectionId' => $section->id,
                'sectionName' => $section->name,
                'users' => $leaderboardUsers,
                'userRank' => $userRank,
                'totalPlayers' => $usersInSection->count(),
            ];
        }

        return response()->json([
            'leaderboards' => $sectionLeaderboards,
            'selectedSeason' => [
                'id' => $season->id,
                'name' => $season->name,
            ],
        ]);
    })->middleware(['auth', 'verified'])->name('api.leaderboard');

    // ─────────────────────────────────────────────
    // Dashboard Exams API (season-scoped)
    // ─────────────────────────────────────────────
    Route::get('api/dashboard-exams', function () {
        $user = auth()->user();
        $seasonId = request()->integer('season_id');
        $season = $seasonId ? Season::find($seasonId) : Season::current();

        if (! $season) {
            return response()->json(['exams' => []]);
        }

        $sectionIds = $user->sections()
            ->wherePivot('season_id', $season->id)
            ->pluck('sections.id');

        $upcomingExams = Exam::where('status', '!=', 'draft')
            ->when(! $user->is_admin, function ($query) use ($sectionIds) {
                $query->where(function ($q) use ($sectionIds) {
                    $q->whereNull('section_id')
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
                    'exam_date_iso' => $exam->exam_date->toIso8601String(),
                    'duration_minutes' => $exam->duration_minutes,
                    'status' => $exam->status,
                    'parts_count' => $totalParts,
                    'submitted_parts' => $submittedPartsCount,
                    'is_completed' => $submittedPartsCount === $totalParts && $totalParts > 0,
                ];
            });

        return response()->json(['exams' => $upcomingExams]);
    })->middleware(['auth', 'verified'])->name('api.dashboard-exams');

    Route::post('api/chat', ChatController::class)->middleware('throttle:60,1')->name('chat');
    Route::get('api/chat/history', [ChatController::class, 'getHistory'])->middleware('throttle:60,1')->name('chat.history');

    // Games hub
    Route::get('games', [GamesController::class, 'index'])->middleware('student.page:games')->name('games.index');

    // Interactive Maps
    Route::get('maps', [LearningMapController::class, 'index'])->middleware('student.page:maps')->name('maps.index');
    Route::post('maps/nodes/{slug}/complete', [LearningMapController::class, 'complete'])
        ->middleware('student.page:maps')
        ->name('maps.nodes.complete');

    // Tower Defense game routes
    // ─────────────────────────────────────────────
    // Courses (LMS Learning Portal)
    // ─────────────────────────────────────────────
    Route::get('courses', [CourseController::class, 'index'])
        ->middleware('student.page:courses')
        ->name('courses.index');
    Route::get('courses/{course}', [CourseController::class, 'show'])
        ->middleware('student.page:courses')
        ->name('courses.show');
    Route::get('courses/{course}/lessons/{lesson}', [CourseController::class, 'lesson'])
        ->middleware('student.page:courses')
        ->name('courses.lesson');
    Route::post('courses/{course}/lessons/{lesson}/quiz', [CourseController::class, 'submitQuiz'])
        ->middleware(['student.page:courses', 'throttle:10,1'])
        ->name('courses.lesson.quiz');

    Route::prefix('games/tower-defense')->name('games.tower-defense.')->group(function () {
        Route::get('/', [TowerDefenseController::class, 'index'])->middleware('student.page:games')->name('index');
        Route::get('/play/{level}', [TowerDefenseController::class, 'play'])->middleware('student.page:games')->name('play');
        Route::post('/runs', [TowerDefenseController::class, 'startRun'])->middleware(['student.page:games', 'throttle:30,1'])->name('runs.start');
        Route::post('/runs/{run}/finish', [TowerDefenseController::class, 'finishRun'])->middleware(['student.page:games', 'throttle:30,1'])->name('runs.finish');
        Route::get('/leaderboard/{level}', [TowerDefenseController::class, 'leaderboard'])->middleware('student.page:games')->name('leaderboard');
    });

    // Admin routes
    Route::get('admin/exams/submissions', [ExamSubmissionController::class, 'index'])->name('admin.exams.submissions');
    Route::get('admin/exams/{exam}/submissions', [ExamSubmissionController::class, 'examSubmissions'])->name('admin.exams.submissions.by-exam');
});

require __DIR__.'/settings.php';
