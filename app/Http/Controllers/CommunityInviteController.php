<?php

namespace App\Http\Controllers;

use App\Models\CommunityInvite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommunityInviteController extends Controller
{
    /**
     * Accept a personalized community lounge invite token.
     */
    public function accept(Request $request, string $token): RedirectResponse
    {
        $viewer = $request->user();

        if (! $viewer) {
            return redirect()->guest(route('login'))
                ->with('status', 'Please sign in to activate your lounge invite.');
        }

        $token = trim($token);
        $invite = CommunityInvite::query()
            ->with('user')
            ->where('token', $token)
            ->first();

        if (! $invite) {
            return redirect()->route('community.index')
                ->with('error', 'Invalid or expired community lounge invite link.');
        }

        if ((int) $invite->user_id !== (int) $viewer->id) {
            return redirect()->route('community.index')
                ->with('error', 'This invite link is reserved for another student.');
        }

        $isFirstTime = ! $invite->isAccepted();

        if (! $invite->isAccepted()) {
            $invite->update(['accepted_at' => now()]);
        }

        return redirect()->route('community.index')
            ->with('first_time_lounge_join', $isFirstTime)
            ->with('success', 'Welcome to the Community Lounge! Your access has been unlocked.');
    }

    /**
     * Redeem an invite code or token from the locked gate screen.
     */
    public function joinByCode(Request $request): JsonResponse|RedirectResponse
    {
        $viewer = $request->user();
        abort_unless($viewer, 401);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255'],
        ]);

        $rawInput = trim($validated['code']);

        // Extract token if user pasted the full URL
        if (str_contains($rawInput, '/community/join/')) {
            $parts = explode('/community/join/', $rawInput);
            $token = trim(end($parts), '/ ');
        } else {
            $token = trim($rawInput, '/ ');
        }

        $invite = CommunityInvite::query()
            ->where('token', $token)
            ->first();

        if (! $invite || (int) $invite->user_id !== (int) $viewer->id) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Invalid or expired invite code for your account.',
                ], 422);
            }

            return back()->withErrors([
                'code' => 'Invalid or expired invite code for your account.',
            ]);
        }

        $isFirstTime = ! $invite->isAccepted();

        if (! $invite->isAccepted()) {
            $invite->update(['accepted_at' => now()]);
        }

        $request->session()->flash('first_time_lounge_join', $isFirstTime);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'unlocked',
                'first_time_join' => $isFirstTime,
                'redirect' => route('community.index'),
            ]);
        }

        return redirect()->route('community.index')
            ->with('first_time_lounge_join', $isFirstTime)
            ->with('success', 'Welcome to the Community Lounge! Your access has been unlocked.');
    }
}
