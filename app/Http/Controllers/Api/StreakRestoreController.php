<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RestoreStreakRequest;
use App\Services\StreakRestoreService;
use Illuminate\Http\JsonResponse;

class StreakRestoreController extends Controller
{
    public function __construct(
        protected StreakRestoreService $streakRestoreService,
    ) {}

    public function __invoke(RestoreStreakRequest $request): JsonResponse
    {
        $result = $this->streakRestoreService->restore(
            $request->user(),
            $request->validated()['date'],
        );

        return response()->json(
            $result,
            $result['restored'] ? 200 : 422,
        );
    }
}
