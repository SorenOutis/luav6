<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\PlatformMaintenance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceStatusController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'enabled' => PlatformMaintenance::isEnabled(),
        ]);
    }
}
