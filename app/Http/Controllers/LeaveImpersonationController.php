<?php

namespace App\Http\Controllers;

use App\Support\Impersonation;
use Illuminate\Http\RedirectResponse;

class LeaveImpersonationController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        if (! Impersonation::isImpersonating()) {
            return redirect()->route('dashboard');
        }

        Impersonation::leave();

        return redirect(session()->pull(Impersonation::BACK_TO_KEY) ?: '/admin/users');
    }
}
