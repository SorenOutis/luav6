<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\Admin\ExamSubmissionController;
use App\Http\Controllers\AnonymousMessageController;
use App\Http\Controllers\Api\ClaimXpController;
use App\Http\Controllers\Api\DashboardExamsController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\LeaderboardToggleBlurController;
use App\Http\Controllers\Api\XpHistoryController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChatHistoryController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\FaviconController;
use App\Http\Controllers\Games\GamesController;
use App\Http\Controllers\Games\TowerDefenseController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\HowItWorksController;
use App\Http\Controllers\LeaderboardController as LeaderboardPageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PublicProfileController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

// ─── Public routes ────────────────────────────────────────────────────────

Route::get('/', WelcomeController::class)->name('home');
Route::get('/about', AboutController::class)->name('about');
Route::get('/how-it-works', HowItWorksController::class)->name('how-it-works');

// ─── Crawler-facing routes ────────────────────────────────────────────────

Route::get('/robots.txt', RobotsController::class);
Route::get('/sitemap.xml', SitemapController::class);

// ─── Branding ───────────────────────────────────────────────────────────────

// Serves the uploaded school logo as the site favicon (falling back to the
// bundled /favicon.ico when no logo is set).
Route::get('/favicon.png', FaviconController::class)->name('favicon');

// ─── Authenticated routes ─────────────────────────────────────────────────

Route::middleware(['auth', 'verified', 'banned.redirect'])->group(function () {
    Route::get('grades', [GradeController::class, 'index'])
        ->middleware('student.page:grades')
        ->name('grades');
    Route::get('api/grades', [GradeController::class, 'apiIndex'])
        ->middleware('student.page:grades')
        ->name('api.grades');

    Route::get('dashboard', DashboardController::class)
        ->middleware('student.page:dashboard')
        ->name('dashboard');

    Route::get('leaderboard', LeaderboardPageController::class)
        ->middleware('student.page:leaderboard')
        ->name('leaderboard');

    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');

    Route::get('u/{user}', [PublicProfileController::class, 'show'])->name('users.show');
    Route::get('users/{user}/xp-history', XpHistoryController::class)->name('users.xp-history');
    Route::patch('profile/section', [ProfileController::class, 'updateSection'])->name('profile.section.update');
    Route::post('sections/join-by-code', [ProfileController::class, 'joinByCode'])->name('sections.join-by-code');
    Route::post('sections/{section}/verify-password', [ProfileController::class, 'verifySectionPassword'])->name('sections.verify-password');

    Route::post('api/leaderboard/toggle-blur', LeaderboardToggleBlurController::class)
        ->middleware(['auth', 'verified'])
        ->name('api.leaderboard.toggle-blur');

    Route::get('assignments', [AssignmentController::class, 'index'])->middleware('student.page:assignments')->name('assignments.index');
    Route::post('assignments/{assignment}/submit', [AssignmentController::class, 'store'])->middleware('student.page:assignments')->name('assignments.submit');

    Route::get('exams', [ExamController::class, 'index'])->middleware('student.page:exams')->name('exams.index');
    Route::get('exams/{exam}', [ExamController::class, 'show'])->middleware('student.page:exams')->name('exams.show');
    Route::post('exams/pre-warm-ai', [ExamController::class, 'preWarmAI'])->middleware('student.page:exams')->name('exams.preWarmAI');
    Route::post('exams/{exam}/monitor-progress', [ExamController::class, 'monitorProgress'])->middleware(['student.page:exams', 'throttle:exams.progress'])->name('exams.monitorProgress');
    // ℹ️ Scope binding via explicit controller check rather than
    // ->scopeBindings(), because the route parameter `{examPart}` does not
    // match the relationship name (`parts`) and Laravel's automatic scoping
    // would call the non-existent method Exam::examParts().
    Route::post('exams/{exam}/parts/{examPart}/start', [ExamController::class, 'startPart'])
        ->middleware(['student.page:exams', 'throttle:exams.start'])
        ->name('exams.startPart');
    Route::put('exams/{exam}/parts/{examPart}/answers', [ExamController::class, 'saveAnswers'])
        ->middleware(['student.page:exams', 'throttle:exams.answers'])
        ->name('exams.saveAnswers');
    Route::post('exams/{exam}/parts/{examPart}/submit', [ExamController::class, 'submitPart'])
        ->middleware(['student.page:exams', 'throttle:exams.submit'])
        ->name('exams.submitPart');
    Route::get('exams/{exam}/parts/{examPart}/status', [ExamController::class, 'partStatus'])
        ->middleware(['student.page:exams', 'throttle:exams.status'])
        ->name('exams.partStatus');

    Route::get('ngl', [AnonymousMessageController::class, 'index'])->middleware('student.page:ngl')->name('ngl.index');
    Route::post('ngl', [AnonymousMessageController::class, 'store'])->middleware('student.page:ngl')->name('ngl.store');
    Route::post('ngl/{message}/like', [AnonymousMessageController::class, 'like'])->middleware('student.page:ngl')->name('ngl.like');

    // ─── API routes (season-scoped) ─────────────────────────────────
    Route::get('api/leaderboard', LeaderboardController::class)
        ->middleware(['auth', 'verified'])
        ->name('api.leaderboard');

    Route::get('api/dashboard-exams', DashboardExamsController::class)
        ->middleware(['auth', 'verified'])
        ->name('api.dashboard-exams');

    // Daily XP Claim
    Route::post('api/claim-xp', ClaimXpController::class)
        ->middleware(['auth', 'verified', 'throttle:10,1'])
        ->name('api.claim-xp');

    Route::post('api/claim-xp/prompt-shown', [ClaimXpController::class, 'promptShown'])
        ->middleware(['auth', 'verified', 'throttle:10,1'])
        ->name('api.claim-xp.prompt-shown');

    Route::post('api/chat', ChatController::class)->middleware('throttle:60,1')->name('chat');
    Route::post('api/chat/stream', [ChatController::class, 'stream'])->middleware('throttle:60,1')->name('chat.stream');
    Route::get('api/chat/history', [ChatController::class, 'getHistory'])->middleware('throttle:60,1')->name('chat.history');
    Route::post('api/chat/clear', [ChatController::class, 'clearHistory'])->middleware('throttle:60,1')->name('chat.clear');

    // Chats history (persisted conversations from the AI widget)
    Route::get('chats', [ChatHistoryController::class, 'index'])
        ->middleware('student.page:chats')
        ->name('chats.index');
    Route::get('chats/{session}', [ChatHistoryController::class, 'show'])
        ->middleware('student.page:chats')
        ->name('chats.show');
    Route::post('api/chats', [ChatHistoryController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('chats.store');
    Route::post('api/chats/{session}/messages', [ChatHistoryController::class, 'message'])
        ->middleware('throttle:60,1')
        ->name('chats.message');
    Route::post('api/chats/{session}/stream', [ChatHistoryController::class, 'stream'])
        ->middleware('throttle:60,1')
        ->name('chats.stream');
    Route::delete('api/chats/{session}', [ChatHistoryController::class, 'destroy'])
        ->middleware('throttle:60,1')
        ->name('chats.destroy');

    // Games hub
    Route::get('games', [GamesController::class, 'index'])->middleware('student.page:games')->name('games.index');

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
