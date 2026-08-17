<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\StudentActivityNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProfileKudoController extends Controller
{
    /** @var array<string, string> */
    private const KUDOS = [
        'great-work' => 'Great work',
        'on-fire' => 'On fire',
        'keep-going' => 'Keep going',
    ];

    public function store(Request $request, User $user): RedirectResponse
    {
        $sender = $request->user();
        abort_if($sender->is($user), 422, 'You cannot send yourself a kudo.');
        abort_unless(
            $sender->can('interactWithProfile', $user),
            403,
            'You can only send kudos to visible student profiles in one of your sections.',
        );

        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys(self::KUDOS))],
        ]);

        $existing = DB::table('profile_kudos')
            ->where('sender_id', $sender->id)
            ->where('recipient_id', $user->id)
            ->first();

        DB::table('profile_kudos')->updateOrInsert(
            ['sender_id' => $sender->id, 'recipient_id' => $user->id],
            ['type' => $data['type'], 'updated_at' => now(), 'created_at' => $existing?->created_at ?? now()],
        );

        if (! $existing || $existing->type !== $data['type']) {
            $user->notify(new StudentActivityNotification([
                'type' => 'kudo',
                'icon' => 'sparkles',
                'title' => 'You received a kudo',
                'message' => "{$sender->name} sent you “".self::KUDOS[$data['type']].'”.',
                'meta' => 'Social',
                'image' => $sender->avatar,
                'href' => "/u/{$sender->public_id}",
            ]));
        }

        return back();
    }

}
