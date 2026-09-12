<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Laravel\Fortify\Features;

class PrivacyPolicyController extends Controller
{
    public function __invoke()
    {
        return inertia('Privacy', [
            'canRegister' => Features::enabled(Features::registration()) && (bool) Setting::get('registration_enabled', true),
        ]);
    }
}
