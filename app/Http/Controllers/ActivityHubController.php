<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityHubController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Activities/Index', [
            'examsBySeason' => [],
            'examPagination' => ['hasMore' => false, 'nextCursor' => null],
            'assignments' => [],
            'courses' => [],
            'sectionTabs' => [['key' => 'all', 'label' => 'All sections', 'count' => 0]],
            'unifiedTimeline' => [],
            'hubStats' => [
                'total' => 0,
                'pending' => 0,
                'completed' => 0,
                'exams' => ['total' => 0, 'pending' => 0, 'completed' => 0],
                'assignments' => ['total' => 0, 'pending' => 0, 'completed' => 0],
                'courses' => ['total' => 0, 'pending' => 0, 'completed' => 0],
            ],
        ]);
    }

    public function listing(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [],
            'meta' => ['hasMore' => false, 'nextCursor' => null],
        ]);
    }
}
