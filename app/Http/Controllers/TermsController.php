<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Laravel\Fortify\Features;

class TermsController extends Controller
{
    public function __invoke()
    {
        return inertia('Terms', [
            'canRegister' => Features::enabled(Features::registration()) && (bool) Setting::get('registration_enabled', true),
        ]);
    }
}
