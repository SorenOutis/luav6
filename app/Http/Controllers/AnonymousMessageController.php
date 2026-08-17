<?php

namespace App\Http\Controllers;

use App\Models\AnonymousMessage;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AnonymousMessageController extends Controller
{
    private const FEED_PAGE_SIZE = 24;

    public function index()
    {
        $page = $this->feedPage(auth()->id());

        return Inertia::render('Ngl', [
            'messages' => $page['data'],
            'userLikedMessageIds' => $page['userLikedMessageIds'],
            'pagination' => $page['meta'],
        ]);
    }

    public function feed(Request $request)
    {
        return response()->json($this->feedPage(
            $request->user()->id,
            $request->query('cursor'),
        ));
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

    /**
     * @return array{data: array<int, array<string, mixed>>, userLikedMessageIds: array<int, int>, meta: array{hasMore: bool, nextCursor: string|null}}
     */
    private function feedPage(int $userId, ?string $cursor = null): array
    {
        $paginator = AnonymousMessage::query()
            ->where('is_approved', true)
            ->withCount('likedByUsers as likes_count')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(
                self::FEED_PAGE_SIZE,
                ['*'],
                'cursor',
                Cursor::fromEncoded($cursor),
            );

        $messages = collect($paginator->items());
        $messageIds = $messages->pluck('id');

        return [
            'data' => $messages->map(fn (AnonymousMessage $message) => [
                'id' => $message->id,
                'content' => $message->content,
                'likes_count' => (int) $message->likes_count,
                'created_at' => $message->created_at?->toIso8601String(),
            ])->values()->all(),
            // Only fetch likes for rows in this page; a long-lived account can
            // otherwise accumulate an unbounded array on every feed visit.
            'userLikedMessageIds' => DB::table('anonymous_message_likes')
                ->where('user_id', $userId)
                ->whereIn('anonymous_message_id', $messageIds)
                ->pluck('anonymous_message_id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all(),
            'meta' => [
                'hasMore' => $paginator->hasMorePages(),
                'nextCursor' => $paginator->nextCursor()?->encode(),
            ],
        ];
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
