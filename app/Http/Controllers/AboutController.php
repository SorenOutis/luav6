<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamSubmission;
use App\Models\Setting;
use App\Models\User;
use Laravel\Fortify\Features;

class AboutController extends Controller
{
    public function __invoke()
    {
        return inertia('About', [
            'canRegister' => Features::enabled(Features::registration()) && (bool) Setting::get('registration_enabled', true),
            'totalUsers' => User::count(),
            'totalExams' => Exam::where('status', '!=', 'draft')->count(),
            'totalSubmissions' => ExamSubmission::count(),
        ]);
    }
}
