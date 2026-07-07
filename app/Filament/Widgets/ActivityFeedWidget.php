<?php

namespace App\Filament\Widgets;

use App\Models\ExamSubmission;
use App\Models\User;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class ActivityFeedWidget extends Widget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.activity-feed';

    protected ?string $pollingInterval = '60s';

    protected function getViewData(): array
    {
        $cutoff = now()->subHours(24);

        // New registrations
        $registrations = User::query()
            ->where('is_admin', false)
            ->forWorkspace()
            ->where('created_at', '>=', $cutoff)
            ->orderByDesc('created_at')
            ->limit(15)
            ->get()
            ->toBase()
            ->map(fn ($u) => [
                'type' => 'registration',
                'user_name' => $u->name,
                'description' => 'New student registered',
                'timestamp' => $u->created_at,
                'icon' => 'heroicon-m-user-plus',
            ]);

        // Exam submissions
        $examSubmissions = ExamSubmission::query()
            ->with(['user:id,name', 'exam:id,title'])
            ->where('created_at', '>=', $cutoff)
            ->orderByDesc('created_at')
            ->limit(15)
            ->get()
            ->toBase()
            ->map(fn ($s) => [
                'type' => 'exam',
                'user_name' => $s->user?->name ?? 'Unknown',
                'description' => 'Submitted: '.($s->exam?->title ?? 'Exam'),
                'timestamp' => $s->created_at,
                'icon' => 'heroicon-m-document-check',
            ]);

        // Badge awards
        $badges = DB::table('badge_user')
            ->join('users', 'badge_user.user_id', '=', 'users.id')
            ->join('badges', 'badge_user.badge_id', '=', 'badges.id')
            ->where('badge_user.created_at', '>=', $cutoff)
            ->orderByDesc('badge_user.created_at')
            ->limit(15)
            ->get(['badge_user.created_at', 'users.name as user_name', 'badges.name as badge_name', 'users.id as user_id'])
            ->map(fn ($b) => [
                'type' => 'badge',
                'user_name' => $b->user_name,
                'description' => 'Earned badge: '.($b->badge_name ?? 'Badge'),
                'timestamp' => $b->created_at,
                'icon' => 'heroicon-m-star',
            ]);

        // Assignment submissions
        $assignmentSubmissions = DB::table('assignment_user')
            ->join('users', 'assignment_user.user_id', '=', 'users.id')
            ->join('assignments', 'assignment_user.assignment_id', '=', 'assignments.id')
            ->where('assignment_user.submitted', true)
            ->where('assignment_user.updated_at', '>=', $cutoff)
            ->orderByDesc('assignment_user.updated_at')
            ->limit(15)
            ->get()
            ->map(fn ($a) => [
                'type' => 'assignment',
                'user_name' => $a->name,
                'description' => 'Submitted assignment: '.($a->title ?? 'Assignment'),
                'timestamp' => $a->updated_at,
                'icon' => 'heroicon-m-clipboard-document-check',
            ]);

        // Merge all, sort by timestamp, limit to 15
        $all = $registrations->merge($examSubmissions)->merge($badges)->merge($assignmentSubmissions)
            ->sortByDesc('timestamp')
            ->take(15)
            ->values()
            ->map(function ($event) {
                $ts = $event['timestamp'];
                $human = $ts instanceof \DateTimeInterface ? Carbon::instance($ts)->diffForHumans() : (string) $ts;

                return array_merge($event, ['timestamp' => $human]);
            });

        return [
            'activities' => $all,
        ];
    }
}
