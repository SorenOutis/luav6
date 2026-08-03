<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Exam;
use App\Models\ExamSubmission;
use App\Models\Season;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Features;

class WelcomeController extends Controller
{
    public function __invoke()
    {
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
            'demoVideoUrl' => filled($demoVideoPath) ? Storage::disk('public')->url($demoVideoPath) : null,
        ]);
    }
}
