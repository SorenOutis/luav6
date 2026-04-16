<?php

namespace App\Http\Controllers;

use App\Models\AnonymousMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AnonymousMessageController extends Controller
{
    public function index()
    {
        $messages = AnonymousMessage::where('is_approved', true)
            ->withCount('likedByUsers as likes_count')
            ->orderBy('created_at', 'desc')
            ->get();

        $userLikedMessageIds = DB::table('anonymous_message_likes')
            ->where('user_id', auth()->id())
            ->pluck('anonymous_message_id')
            ->toArray();

        return Inertia::render('Ngl', [
            'messages' => $messages,
            'userLikedMessageIds' => $userLikedMessageIds,
        ]);
    }

    public function like(AnonymousMessage $message)
    {
        $userId = auth()->id();

        $like = DB::table('anonymous_message_likes')
            ->where('anonymous_message_id', $message->id)
            ->where('user_id', $userId)
            ->first();

        if ($like) {
            DB::table('anonymous_message_likes')
                ->where('anonymous_message_id', $message->id)
                ->where('user_id', $userId)
                ->delete();
            $message->decrement('likes_count');
        } else {
            DB::table('anonymous_message_likes')->insert([
                'anonymous_message_id' => $message->id,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $message->increment('likes_count');
        }

        return back();
    }

    public function store(Request $request)
    {
        $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        AnonymousMessage::create([
            'user_id' => auth()->id(),
            'content' => $request->content,
            'is_approved' => false,
        ]);

        return back()->with('success', 'Your anonymous message has been sent for approval!');
    }
}
