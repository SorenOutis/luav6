<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class XpHistoryController extends Controller
{
    private const PAGE_SIZE = 30;

    public function __invoke(User $user)
    {
        Gate::authorize('viewProfileActivity', $user);

        $paginator = $user->gamificationHistories()
            ->with('section:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(self::PAGE_SIZE);

        return response()->json([
            'data' => collect($paginator->items())->map(fn ($history) => [
                'id' => $history->id,
                'amount_xp' => (float) $history->amount_xp,
                'reason' => $history->reason,
                'description' => $history->description,
                'section_name' => $history->section?->name,
                'created_at' => $history->created_at->format('M d, Y H:i'),
            ])->values()->all(),
            'meta' => [
                'hasMore' => $paginator->hasMorePages(),
                'nextCursor' => $paginator->nextCursor()?->encode(),
            ],
        ]);
    }
}
