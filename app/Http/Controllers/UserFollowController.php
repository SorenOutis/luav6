<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\UserFollowedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserFollowController extends Controller
{
    public function store(Request $request, User $user): RedirectResponse
    {
        $follower = $request->user();

        abort_if($follower->is($user), 422, 'You cannot follow your own profile.');
        abort_unless($this->sharesSection($follower, $user), 403, 'You can only follow students in one of your sections.');

        // syncWithoutDetaching makes repeated taps harmless and prevents a
        // duplicate notification when an old request is replayed.
        $wasFollowing = $follower->following()->whereKey($user->id)->exists();
        $follower->following()->syncWithoutDetaching([$user->id]);

        if (! $wasFollowing) {
            $user->notify(new UserFollowedNotification(
                $follower->id,
                $follower->name,
                $follower->avatar,
            ));
        }

        return back();
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $request->user()->following()->detach($user->id);

        return back();
    }

    private function sharesSection(User $first, User $second): bool
    {
        return $first->sections()
            ->whereIn('sections.id', $second->sections()->select('sections.id'))
            ->exists();
    }
}
