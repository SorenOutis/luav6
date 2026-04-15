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
        
        $user->load(['sections', 'badges' => function ($q) use ($currentSeason) {
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

            $primarySectionId = $user->sections()->first()?->id;

            $userRank = SeasonProgress::where('season_id', $currentSeason->id)
                ->whereHas('user', function($q) use ($primarySectionId) {
                    $q->where('is_admin', false);
                    if ($primarySectionId) {
                        $q->whereHas('sections', function($sq) use ($primarySectionId) {
                            $sq->where('sections.id', $primarySectionId);
                        });
                    }
                })
                ->where('exp', '>', $seasonalExp)
                ->count() + 1;
            
            $totalPlayers = SeasonProgress::where('season_id', $currentSeason->id)
                ->whereHas('user', function($q) use ($primarySectionId) {
                    $q->where('is_admin', false);
                    if ($primarySectionId) {
                        $q->whereHas('sections', function($sq) use ($primarySectionId) {
                            $sq->where('sections.id', $primarySectionId);
                        });
                    }
                })
                ->count();
        }

        $viewerSectionIds = $viewer->sections()->pluck('sections.id')->toArray();
        $userSectionIds = $user->sections()->pluck('sections.id')->toArray();
        $sharedSections = array_intersect($viewerSectionIds, $userSectionIds);
        $isSameSection = !empty($sharedSections);

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

        $history = $user->gamificationHistories()
            ->with(['section', 'season'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(fn($h) => [
                'id' => $h->id,
                'amount_xp' => (float) $h->amount_xp,
                'amount_points' => (float) $h->amount_points,
                'reason' => $h->reason,
                'description' => $h->description,
                'date' => $h->created_at->diffForHumans(),
                'full_date' => $h->created_at->format('M d, Y h:i A'),
                'section' => $h->section?->name,
            ]);

        return Inertia::render('User/PublicProfile', [
            'profileUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->avatar,
                'cover_photo' => $user->cover_photo,
                'sections' => $user->sections->map(fn($s) => $s->name)->toArray(),
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
            'history' => $history,
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
