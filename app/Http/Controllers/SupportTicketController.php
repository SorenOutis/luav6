<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Services\AdminNotificationService;
use App\Support\PublicFileUrl;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SupportTicketController extends Controller
{
    public const ALLOWED_MIMES = 'jpg,jpeg,png,webp,pdf,doc,docx,txt';

    public const MAX_ATTACHMENT_KB = 10240;

    public const MAX_TICKETS_PER_DAY = 3;

    public function index(Request $request)
    {
        $user = $request->user();

        $tickets = SupportTicket::query()
            ->withoutGlobalScope('workspace')
            ->where('user_id', $user->id)
            ->with(['replies.user:id,name,avatar'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (SupportTicket $ticket) => $this->ticketPayload($ticket));

        $ticketsUsedToday = $this->countTodayTickets((int) $user->id);

        return Inertia::render('Support', [
            'tickets' => $tickets,
            'categories' => SupportTicket::CATEGORIES,
            'priorities' => SupportTicket::PRIORITIES,
            'dailyTicketLimit' => self::MAX_TICKETS_PER_DAY,
            'ticketsUsedToday' => $ticketsUsedToday,
        ]);
    }

    public function store(Request $request)
    {
        if ($this->countTodayTickets((int) $request->user()->id) >= self::MAX_TICKETS_PER_DAY) {
            return back()->withErrors([
                'limit' => 'You can only submit '.self::MAX_TICKETS_PER_DAY.' tickets per day. Please try again tomorrow.',
            ]);
        }

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'category' => 'required|string|in:'.implode(',', array_keys(SupportTicket::CATEGORIES)),
            'priority' => 'required|string|in:'.implode(',', array_keys(SupportTicket::PRIORITIES)),
            'message' => 'required|string|max:5000',
            'attachment' => 'nullable|file|mimes:'.self::ALLOWED_MIMES.'|max:'.self::MAX_ATTACHMENT_KB,
        ]);

        $subject = $this->cleanLine($validated['subject'], 255);
        $message = $this->cleanText($validated['message'], 5000);

        $sanitizationErrors = [];

        if ($subject === '') {
            $sanitizationErrors['subject'] = 'The subject must contain visible text.';
        }

        if ($message === '') {
            $sanitizationErrors['message'] = 'The message must contain visible text.';
        }

        if ($sanitizationErrors !== []) {
            throw ValidationException::withMessages($sanitizationErrors);
        }

        $attachmentPath = null;

        if ($request->hasFile('attachment') && $request->file('attachment')->isValid()) {
            $attachmentPath = $request->file('attachment')->store(
                'support-tickets/'.$request->user()->id,
                'public'
            );
        }

        $ticket = SupportTicket::create([
            'user_id' => $request->user()->id,
            'subject' => $subject,
            'category' => $validated['category'],
            'priority' => $validated['priority'],
            'message' => $message,
            'attachment_path' => $attachmentPath,
            'status' => SupportTicket::STATUS_OPEN,
        ]);

        AdminNotificationService::notifyAdmins(
            'New support ticket',
            "{$request->user()->name}: {$ticket->subject}",
            $ticket,
            'heroicon-o-lifebuoy',
            'warning',
            '/admin/support-tickets',
        );

        return back()->with('success', 'Your concern has been submitted. Our team will get back to you soon.');
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $user = $request->user();

        abort_unless((int) $ticket->user_id === (int) $user->id, 403);

        abort_if($ticket->isResolved(), 403, 'This conversation is closed because the ticket is marked as resolved.');

        $validated = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $replyMessage = $this->cleanText($validated['message'], 2000);

        if ($replyMessage === '') {
            throw ValidationException::withMessages([
                'message' => 'The reply must contain visible text.',
            ]);
        }

        $reply = $ticket->replies()->create([
            'user_id' => $user->id,
            'message' => $replyMessage,
        ]);

        AdminNotificationService::notifyAdmins(
            'Support ticket reply',
            "{$user->name} replied: {$ticket->subject}",
            $ticket,
            'heroicon-o-chat-bubble-left-right',
            'info',
            '/admin/support-tickets',
        );

        return back()->with('success', 'Your reply has been sent.');
    }

    /**
     * Tickets the user successfully submitted today (calendar day). Used to
     * enforce the daily submission cap.
     */
    private function countTodayTickets(int $userId): int
    {
        return SupportTicket::query()
            ->withoutGlobalScope('workspace')
            ->where('user_id', $userId)
            ->whereDate('created_at', today())
            ->count();
    }

    /**
     * Strip HTML tags and collapse whitespace on a single-line field so no
     * markup or script content is ever persisted.
     */
    private function cleanLine(?string $value, int $max): string
    {
        $clean = trim(strip_tags((string) $value));
        $clean = (string) preg_replace('/\s+/u', ' ', $clean);

        return mb_substr($clean, 0, $max);
    }

    /**
     * Strip HTML tags and normalize line endings on a multi-line field,
     * preserving single paragraph breaks.
     */
    private function cleanText(?string $value, int $max): string
    {
        $clean = str_replace(["\r\n", "\r"], "\n", (string) $value);
        $clean = trim(strip_tags($clean));
        $clean = (string) preg_replace("/\n{3,}/", "\n\n", $clean);

        return mb_substr($clean, 0, $max);
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketPayload(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'category' => $ticket->category,
            'category_label' => SupportTicket::CATEGORIES[$ticket->category] ?? $ticket->category,
            'priority' => $ticket->priority,
            'priority_label' => SupportTicket::PRIORITIES[$ticket->priority] ?? $ticket->priority,
            'message' => $ticket->message,
            'status' => $ticket->status,
            'status_label' => SupportTicket::STATUSES[$ticket->status] ?? $ticket->status,
            'attachment_url' => PublicFileUrl::resolve($ticket->attachment_path),
            'resolved_at' => $ticket->resolved_at?->toIso8601String(),
            'created_at' => $ticket->created_at?->toIso8601String(),
            'replies' => $ticket->replies->map(fn ($reply) => [
                'id' => $reply->id,
                'message' => $reply->message,
                'created_at' => $reply->created_at?->toIso8601String(),
                'user' => [
                    'id' => $reply->user?->id,
                    'name' => $reply->user?->name,
                    'avatar' => $reply->user?->avatar,
                    'is_mine' => (int) $reply->user_id === (int) $ticket->user_id,
                ],
            ])->values()->all(),
        ];
    }
}
