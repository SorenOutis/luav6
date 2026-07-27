<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;

class XpHistoryController extends Controller
{
    public function __invoke(User $user)
    {
        return $user->gamificationHistories()
            ->with('section:id,name')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($history) {
                return [
                    'id' => $history->id,
                    'amount_xp' => (float) $history->amount_xp,
                    'reason' => $history->reason,
                    'description' => $history->description,
                    'section_name' => $history->section?->name,
                    'created_at' => $history->created_at->format('M d, Y H:i'),
                ];
            });
    }
}
