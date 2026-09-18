<?php

use App\Filament\Resources\SupportTickets\SupportTicketResource;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportReplyService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

test('guest cannot access support page', function () {
    $this->get('/support')->assertRedirect('/login');
});

test('student can view support page with tickets', function () {
    $this->withoutVite();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/support')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Support')
            ->has('tickets')
            ->has('categories')
            ->has('priorities')
        );
});

test('student can submit a support ticket', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($user)->post('/support', [
        'subject' => 'Login is broken',
        'category' => 'technical',
        'priority' => 'high',
        'message' => 'I cannot log in on mobile.',
    ])->assertRedirect();

    $ticket = SupportTicket::query()->where('user_id', $user->id)->first();

    expect($ticket)->not->toBeNull()
        ->and($ticket->subject)->toBe('Login is broken')
        ->and($ticket->status)->toBe(SupportTicket::STATUS_OPEN);

    expect($superAdmin->notifications()->count())->toBeGreaterThan(0);
});

test('student can submit a ticket with attachment', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user)->post('/support', [
        'subject' => 'Screenshot attached',
        'category' => 'general',
        'priority' => 'medium',
        'message' => 'See attached screenshot.',
        'attachment' => UploadedFile::fake()->image('bug.png'),
    ])->assertRedirect();

    $ticket = SupportTicket::query()->where('user_id', $user->id)->first();

    expect($ticket)->not->toBeNull()
        ->and($ticket->attachment_path)->not->toBeNull();

    Storage::disk('public')->assertExists($ticket->attachment_path);
});

test('ticket submission validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/support', [
        'subject' => '',
        'category' => 'invalid',
        'priority' => 'invalid',
        'message' => '',
    ])->assertSessionHasErrors(['subject', 'category', 'priority', 'message']);
});

test('student cannot reply to a resolved ticket', function () {
    $user = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $ticket = SupportTicket::create([
        'user_id' => $user->id,
        'subject' => 'Need help',
        'category' => 'general',
        'priority' => 'low',
        'message' => 'Original concern.',
        'status' => SupportTicket::STATUS_RESOLVED,
        'resolved_by' => $admin->id,
        'resolved_at' => now(),
    ]);

    $this->actingAs($user)->post("/support/{$ticket->id}/reply", [
        'message' => 'Still happening, please help.',
    ])->assertForbidden();

    expect($ticket->fresh()->status)->toBe(SupportTicket::STATUS_RESOLVED)
        ->and($ticket->fresh()->replies)->toHaveCount(0);
});

test('student cannot reply to a closed ticket', function () {
    $user = User::factory()->create();

    $ticket = SupportTicket::create([
        'user_id' => $user->id,
        'subject' => 'Need help',
        'category' => 'general',
        'priority' => 'low',
        'message' => 'Original concern.',
        'status' => SupportTicket::STATUS_CLOSED,
        'resolved_at' => now(),
    ]);

    $this->actingAs($user)->post("/support/{$ticket->id}/reply", [
        'message' => 'One more thing.',
    ])->assertForbidden();

    expect($ticket->fresh()->replies)->toHaveCount(0);
});

test('support page shows resolved date without the resolver name', function () {
    $this->withoutVite();
    $user = User::factory()->create();
    $admin = User::factory()->admin()->create();

    SupportTicket::create([
        'user_id' => $user->id,
        'subject' => 'Need help',
        'category' => 'general',
        'priority' => 'low',
        'message' => 'Original concern.',
        'status' => SupportTicket::STATUS_RESOLVED,
        'resolved_by' => $admin->id,
        'resolved_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/support')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Support')
            ->where('tickets.0.status', SupportTicket::STATUS_RESOLVED)
            ->where('tickets.0.resolved_at', fn ($value) => $value !== null)
            ->missing('tickets.0.resolved_by_name')
        );
});

test('student cannot reply to another student ticket', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $ticket = SupportTicket::create([
        'user_id' => $owner->id,
        'subject' => 'Private concern',
        'category' => 'general',
        'priority' => 'low',
        'message' => 'Private.',
        'status' => SupportTicket::STATUS_OPEN,
    ]);

    $this->actingAs($intruder)->post("/support/{$ticket->id}/reply", [
        'message' => 'Trying to hijack.',
    ])->assertForbidden();
});

test('support ticket resource is visible to admins and super admins only', function () {
    $this->actingAs(User::factory()->admin()->create());
    expect(SupportTicketResource::canAccess())->toBeTrue();

    $this->actingAs(User::factory()->superAdmin()->create());
    expect(SupportTicketResource::canAccess())->toBeTrue();

    $this->actingAs(User::factory()->create());
    expect(SupportTicketResource::canAccess())->toBeFalse();
});

test('admin can resolve a ticket and records who resolved it', function () {
    $student = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $ticket = SupportTicket::create([
        'user_id' => $student->id,
        'subject' => 'Grade missing',
        'category' => 'account',
        'priority' => 'medium',
        'message' => 'My grade is missing.',
        'status' => SupportTicket::STATUS_OPEN,
    ]);

    $this->actingAs($admin);
    $ticket->markResolved($admin, SupportTicket::STATUS_RESOLVED);

    expect($ticket->fresh()->status)->toBe(SupportTicket::STATUS_RESOLVED)
        ->and($ticket->fresh()->resolved_by)->toBe($admin->id)
        ->and($ticket->fresh()->resolved_at)->not->toBeNull();
});

test('ticket subject and message are sanitized of html', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/support', [
        'subject' => '<script>alert(1)</script>  Login   broken',
        'category' => 'technical',
        'priority' => 'high',
        'message' => "<b>Bold</b>\r\n\r\n\r\n<img src=x onerror=alert(1)>Please help.",
    ])->assertRedirect();

    $ticket = SupportTicket::query()->where('user_id', $user->id)->first();

    expect($ticket)->not->toBeNull()
        ->and($ticket->subject)->toBe('alert(1) Login broken')
        ->and($ticket->subject)->not->toContain('<', '>')
        ->and($ticket->message)->not->toContain('<', '>')
        ->and($ticket->message)->toContain('Please help.');
});

test('markup-only submissions are rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/support', [
        'subject' => '<b></b>',
        'category' => 'general',
        'priority' => 'low',
        'message' => '<img src=x>',
    ])->assertSessionHasErrors(['subject', 'message']);

    expect(SupportTicket::query()->where('user_id', $user->id)->count())->toBe(0);
});

test('ticket replies are sanitized of html', function () {
    $user = User::factory()->create();

    $ticket = SupportTicket::create([
        'user_id' => $user->id,
        'subject' => 'Need help',
        'category' => 'general',
        'priority' => 'low',
        'message' => 'Original concern.',
        'status' => SupportTicket::STATUS_OPEN,
    ]);

    $this->actingAs($user)->post("/support/{$ticket->id}/reply", [
        'message' => '<script>alert(1)</script>Still happening.',
    ])->assertRedirect();

    $reply = $ticket->fresh()->replies->first();

    expect($reply->message)->toBe('alert(1)Still happening.')
        ->and($reply->message)->not->toContain('<', '>');
});

test('admin reply is sanitized, stored, and notifies the student', function () {
    $student = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $ticket = SupportTicket::create([
        'user_id' => $student->id,
        'subject' => 'Need help',
        'category' => 'general',
        'priority' => 'low',
        'message' => 'Original concern.',
        'status' => SupportTicket::STATUS_OPEN,
    ]);

    $reply = app(SupportReplyService::class)->createAdminReply(
        $ticket,
        $admin,
        '<b>Hello</b> <img src=x onerror=alert(1)>how can I help?'
    );

    expect($reply->message)->toBe('Hello how can I help?')
        ->and($reply->user_id)->toBe($admin->id);

    $notification = $student->notifications()->first();

    expect($notification)->not->toBeNull()
        ->and($notification->data['title'])->toBe('Support team replied')
        ->and($notification->data['href'])->toBe('/support');
});

test('blank admin reply is rejected without storing or notifying', function () {
    $student = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $ticket = SupportTicket::create([
        'user_id' => $student->id,
        'subject' => 'Need help',
        'category' => 'general',
        'priority' => 'low',
        'message' => 'Original concern.',
        'status' => SupportTicket::STATUS_OPEN,
    ]);

    expect(fn () => app(SupportReplyService::class)->createAdminReply($ticket, $admin, '<br>'))
        ->toThrow(ValidationException::class);

    expect($ticket->fresh()->replies)->toHaveCount(0)
        ->and($student->notifications()->count())->toBe(0);
});

test('student-authored replies do not notify the student', function () {
    $student = User::factory()->create();

    $ticket = SupportTicket::create([
        'user_id' => $student->id,
        'subject' => 'Need help',
        'category' => 'general',
        'priority' => 'low',
        'message' => 'Original concern.',
        'status' => SupportTicket::STATUS_OPEN,
    ]);

    $reply = $ticket->replies()->create([
        'user_id' => $student->id,
        'message' => 'Follow-up from me.',
    ]);

    app(SupportReplyService::class)->notifyStudentOfReply($reply);

    expect($student->notifications()->count())->toBe(0);
});

test('student can only submit three tickets per day', function () {
    $user = User::factory()->create();

    $payload = [
        'subject' => 'Daily concern',
        'category' => 'general',
        'priority' => 'low',
        'message' => 'One more concern.',
    ];

    foreach (range(1, 3) as $i) {
        $this->actingAs($user)->post('/support', [
            ...$payload,
            'subject' => "Daily concern {$i}",
        ])->assertRedirect();
    }

    $this->actingAs($user)->post('/support', $payload)
        ->assertSessionHasErrors(['limit']);

    expect(SupportTicket::query()->where('user_id', $user->id)->count())->toBe(3);
});

test('tickets from previous days do not count toward the daily limit', function () {
    $user = User::factory()->create();

    foreach (range(1, 3) as $i) {
        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'subject' => "Old concern {$i}",
            'category' => 'general',
            'priority' => 'low',
            'message' => 'Old.',
            'status' => SupportTicket::STATUS_CLOSED,
        ]);
        $ticket->forceFill(['created_at' => now()->subDay()])->save();
    }

    $this->actingAs($user)->post('/support', [
        'subject' => 'Fresh concern',
        'category' => 'general',
        'priority' => 'low',
        'message' => 'Today.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(SupportTicket::query()->where('user_id', $user->id)->count())->toBe(4);
});

test('support page exposes the daily ticket allowance', function () {
    $this->withoutVite();
    $user = User::factory()->create();

    SupportTicket::create([
        'user_id' => $user->id,
        'subject' => 'Need help',
        'category' => 'general',
        'priority' => 'low',
        'message' => 'Original concern.',
        'status' => SupportTicket::STATUS_OPEN,
    ]);

    $this->actingAs($user)
        ->get('/support')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Support')
            ->where('dailyTicketLimit', 3)
            ->where('ticketsUsedToday', 1)
        );
});

test('student reply broadcasts a realtime event', function () {
    $user = User::factory()->create();

    $ticket = SupportTicket::create([
        'user_id' => $user->id,
        'subject' => 'Need help',
        'category' => 'general',
        'priority' => 'low',
        'message' => 'Original concern.',
        'status' => SupportTicket::STATUS_OPEN,
    ]);

    $this->actingAs($user)->post("/support/{$ticket->id}/reply", [
        'message' => 'Live follow-up.',
    ])->assertRedirect();

    expect($ticket->fresh()->replies)->toHaveCount(1);
});
