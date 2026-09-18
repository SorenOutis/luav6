<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Laravel\Fortify\Features;

class HowItWorksController extends Controller
{
    public function __invoke()
    {
        return inertia('HowItWorks', [
            'canRegister' => Features::enabled(Features::registration()) && (bool) Setting::get('registration_enabled', true),
        ]);
    }
}
