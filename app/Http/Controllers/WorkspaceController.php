<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function activate(Request $request, Workspace $workspace): RedirectResponse
    {
        $request->user()->activateWorkspace($workspace);

        return back();
    }

    public function inspect(Request $request, Workspace $workspace): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        app(WorkspaceContext::class)->inspect($workspace);

        return redirect('/admin');
    }

    public function stopInspecting(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        app(WorkspaceContext::class)->stopInspecting();

        return redirect('/admin');
    }
}
