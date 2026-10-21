<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommunityController extends Controller
{
    /**
     * Display the community lounge feed.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Community', [
            'workspace' => $request->user()?->currentWorkspace,
        ]);
    }
}
