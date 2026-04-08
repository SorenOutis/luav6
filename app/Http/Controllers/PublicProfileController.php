<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Season;
use App\Models\SeasonProgress;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicProfileController extends Controller
{
    public function show(Request $request, User $user)
    {
        $viewer = $request->user();
        $currentSeason = Season::current();
        
        $user->load(['section', 'badges' => function ($q) use ($currentSeason) {
            if ($currentSeason) {
                $q->wherePivot('season_id', $currentSeason->id);
            }
        }]);

        $seasonalExp = 0;
        $seasonalLevel = 1;
        $userRank = 0;
        $totalPlayers = 0;
        
        if ($currentSeason) {
            $progress = $user->activeSeasonProgress();
            $seasonalExp = $progress?->exp ?? 0;
            $seasonalLevel = $progress?->level ?? 1;

            $userRank = SeasonProgress::where('season_id', $currentSeason->id)
                ->whereHas('user', function($q) use ($user) {
                    $q->where('is_admin', false);
                    if ($user->section_id) {
                        $q->where('section_id', $user->section_id);
                    }
                })
                ->where('exp', '>', $seasonalExp)
                ->count() + 1;
            
            $totalPlayers = SeasonProgress::where('season_id', $currentSeason->id)
                ->whereHas('user', function($q) use ($user) {
                    $q->where('is_admin', false);
                    if ($user->section_id) {
                        $q->where('section_id', $user->section_id);
                    }
                })
                ->count();
        }

        $isSameSection = $viewer->section_id === $user->section_id && $viewer->section_id !== null;

        $courses = [];
        if ($isSameSection && $currentSeason) {
            $courses = $user->courses()
                ->wherePivot('season_id', $currentSeason->id)
                ->get()
                ->map(fn($course) => [
                    'id' => $course->id,
                    'name' => $course->name,
                    'progress' => $course->total_lessons > 0 ? round(($course->pivot->completed_lessons / $course->total_lessons) * 100) : 0,
                    'completedLessons' => $course->pivot->completed_lessons,
                    'totalLessons' => $course->total_lessons,
                    'xpEarned' => $course->pivot->xp_earned,
                ]);
        }

        return Inertia::render('User/PublicProfile', [
            'profileUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->avatar,
                'section' => $user->section?->name,
                'streak' => $user->current_streak ?? 0,
                'joinedAt' => $user->created_at ? $user->created_at->format('M Y') : 'Unknown',
                'isCurrentUser' => $user->id === $viewer->id,
            ],
            'stats' => [
                'level' => $seasonalLevel,
                'xp' => $seasonalExp,
                'rank' => $userRank,
                'totalPlayers' => $totalPlayers,
                'badgesCount' => $user->badges->count(),
            ],
            'badges' => $user->badges->map(fn($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'description' => $b->description,
                'icon' => $b->icon,
            ]),
            'courses' => $courses,
            'isSameSection' => $isSameSection,
        ]);
    }
}
