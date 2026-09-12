<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Laravel\Fortify\Features;

class CookiePolicyController extends Controller
{
    public function __invoke()
    {
        return inertia('Cookies', [
            'canRegister' => Features::enabled(Features::registration()) && (bool) Setting::get('registration_enabled', true),
        ]);
    }
}
