<?php

namespace App\Http\Controllers;

use App\Models\LearningMap\MapNode;
use App\Services\LearningMapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LearningMapController extends Controller
{
    public function __construct(protected LearningMapService $service) {}

    public function index(Request $request)
    {
        $user = $request->user();

        return inertia('Maps/Index', $this->service->payloadForUser($user));
    }

    /**
     * POST /maps/nodes/{slug}/complete
     * Marks the node complete. In production this should only be called
     * from a server-side exam/lesson completion flow, not directly by the client.
     */
    public function complete(Request $request, string $slug)
    {
        $data = $request->validate([
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $node = MapNode::where('slug', $slug)->firstOrFail();
        $this->service->complete(Auth::user(), $node, $data['score'] ?? null);

        return redirect()->route('maps.index');
    }
}
