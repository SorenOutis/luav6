<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Laravel\Fortify\Features;

class WelcomeController extends Controller
{
    public function __invoke()
    {
        return inertia('Welcome', [
            'canRegister' => Features::enabled(Features::registration()) && (bool) Setting::get('registration_enabled', true),
        ]);
    }
}
