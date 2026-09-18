<?php

namespace App\Http\Controllers;

use App\Exceptions\PendingAiActionException;
use App\Models\PendingAiAction;
use App\Services\PendingAiActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PendingAiActionController extends Controller
{
    public function __construct(private readonly PendingAiActionService $actions) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $actions = $this->actions->forUser(
                $request->user(),
                isset($validated['session_id']) ? (int) $validated['session_id'] : null,
            );

            return response()->json([
                'data' => $actions->map(fn (PendingAiAction $action) => $this->actions->present($action))->values(),
            ]);
        } catch (PendingAiActionException $exception) {
            return $this->error($exception);
        }
    }

    public function approve(Request $request, PendingAiAction $action): JsonResponse
    {
        $validated = $request->validate([
            'nonce' => ['required', 'string', 'size:64'],
        ]);

        try {
            $action = $this->actions->approve(
                $action,
                $request->user(),
                $validated['nonce'],
                $this->auditMetadata($request),
            );

            return response()->json([
                'data' => $this->actions->present($action),
            ], $action->status === PendingAiAction::STATUS_EXECUTING ? 202 : 200);
        } catch (PendingAiActionException $exception) {
            return $this->error($exception, $action);
        }
    }

    public function reject(Request $request, PendingAiAction $action): JsonResponse
    {
        $validated = $request->validate([
            'nonce' => ['required', 'string', 'size:64'],
        ]);

        try {
            $action = $this->actions->reject(
                $action,
                $request->user(),
                $validated['nonce'],
                $this->auditMetadata($request),
            );

            return response()->json([
                'data' => $this->actions->present($action),
            ]);
        } catch (PendingAiActionException $exception) {
            return $this->error($exception, $action);
        }
    }

    /** @return array<string, mixed> */
    private function auditMetadata(Request $request): array
    {
        return [
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'request_id' => $request->header('X-Request-ID'),
            'route' => $request->route()?->getName(),
        ];
    }

    private function error(PendingAiActionException $exception, ?PendingAiAction $action = null): JsonResponse
    {
        $payload = ['message' => $exception->getMessage()];
        $freshAction = $action?->fresh();

        if ($freshAction && $freshAction->status !== PendingAiAction::STATUS_PENDING) {
            try {
                $payload['data'] = $this->actions->present($freshAction);
            } catch (PendingAiActionException) {
                // Never leak another user's action while formatting an error.
            }
        }

        return response()->json($payload, $exception->status());
    }
}
