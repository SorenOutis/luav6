<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $user = auth()->user();

        // 1. Announcements (Active)
        $announcements = \App\Models\Announcement::where('is_active', true)->get();

        // 2. Courses (Enrolled by user)
        $courses = $user->courses()->get()->map(function ($course) {
            return [
                'id' => $course->id,
                'name' => $course->name,
                'progress' => $course->total_lessons > 0 ? round(($course->pivot->completed_lessons / $course->total_lessons) * 100) : 0,
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

        // 4. Leaderboard (All users by XP)
        $leaderboardUsers = \App\Models\User::where('is_admin', false)->orderByDesc('exp')->get()->map(function ($u) use ($user) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'xp' => $u->exp,
                'trend' => 'stable', // Can become dynamic later
                'isCurrentUser' => $u->id === $user->id,
            ];
        });

        // Current User Rank among non-admins
        $userRank = \App\Models\User::where('is_admin', false)->where('exp', '>', $user->exp)->count() + 1;
        $totalNonAdmins = \App\Models\User::where('is_admin', false)->count();

        return inertia('Dashboard', [
            'userStats' => [
                'totalXP' => $user->exp,
                'level' => $user->level,
                'currentXP' => $user->exp % 1000, // Assuming 1000 XP per level for simplicity
                'maxXPForLevel' => 1000,
                'rank' => 'Player',
                'rankNumber' => $userRank,
                'totalPlayers' => $totalNonAdmins,
                'achievements' => $user->badges()->count(),
                'points' => $user->points,
            ],
            'announcements' => $announcements,
            'courses' => $courses,
            'assignments' => $assignments,
            'leaderboardUsers' => $leaderboardUsers,
        ]);
    })->name('dashboard');
});

require __DIR__ . '/settings.php';
