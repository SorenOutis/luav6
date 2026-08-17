<?php

use App\Models\AnonymousMessage;
use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use App\Services\ChatService;
use App\Services\LeaderboardService;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

it('paginates chat summaries without loading an unbounded session list', function () {
    $user = User::factory()->create();

    foreach (range(1, 35) as $number) {
        $user->chatSessions()->create(['title' => "Chat {$number}"]);
    }

    actingAs($user)
        ->get(route('chats.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('sessions', 30)
            ->where('sessionPagination.hasMore', true));

    $first = actingAs($user)
        ->getJson(route('chats.sessions'))
        ->assertOk()
        ->assertJsonCount(30, 'data');

    actingAs($user)
        ->getJson(route('chats.sessions', ['cursor' => $first->json('meta.nextCursor')]))
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('meta.hasMore', false);
});

it('loads chat messages in bounded newest-first pages while rendering chronologically', function () {
    $user = User::factory()->create();
    $session = $user->chatSessions()->create(['title' => 'Long conversation']);

    foreach (range(1, 85) as $number) {
        $session->messages()->create([
            'role' => $number % 2 ? 'user' : 'assistant',
            'content' => "Message {$number}",
        ]);
    }

    $response = actingAs($user)->get(route('chats.show', $session));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->has('activeSession.messages', 80)
        ->where('activeSession.messages.0.content', 'Message 6')
        ->where('activeSession.messages.79.content', 'Message 85')
        ->where('activeSession.messagePagination.hasMore', true));

    $beforeId = $session->messages()->where('content', 'Message 6')->value('id');

    actingAs($user)
        ->getJson(route('chats.messages', ['session' => $session, 'before_id' => $beforeId]))
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('data.0.content', 'Message 1')
        ->assertJsonPath('meta.hasMore', false);
});

it('caps persisted AI context independently from durable chat history', function () {
    $user = User::factory()->create();
    $session = $user->chatSessions()->create(['title' => 'Provider context']);

    foreach (range(1, 55) as $number) {
        $session->messages()->create([
            'role' => $number % 2 ? 'user' : 'assistant',
            'content' => "Turn {$number}",
        ]);
    }

    $context = app(ChatService::class)->contextMessages($session);

    expect($context)->toHaveCount(ChatService::MAX_CONTEXT_MESSAGES)
        ->and($context[0]['content'])->toBe('Turn 16')
        ->and($context[39]['content'])->toBe('Turn 55')
        ->and($session->messages()->count())->toBe(55);
});

it('aggregates dashboard history in SQL and returns only the latest thirty entries', function () {
    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create();
    $user = User::factory()->create();
    $user->sections()->attach($section->id, ['season_id' => $season->id]);

    foreach (range(1, 40) as $number) {
        DB::table('gamification_histories')->insert([
            'user_id' => $user->id,
            'section_id' => $section->id,
            'season_id' => $season->id,
            'amount_xp' => 1,
            'amount_points' => 0,
            'reason' => 'Lesson Complete',
            'description' => "Lesson {$number}",
            'created_at' => now()->subMinutes(40 - $number),
            'updated_at' => now()->subMinutes(40 - $number),
        ]);
    }

    actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('xpHistory', 30)
            ->where('statsBreakdown.xp.0.label', 'Lesson Complete')
            ->where('statsBreakdown.xp.0.amount', 40)
            ->where('statsBreakdown.xp.0.count', 40));
});

it('paginates the anonymous-message feed and only returns likes for the page', function () {
    $user = User::factory()->create();

    foreach (range(1, 30) as $number) {
        AnonymousMessage::create([
            'user_id' => $user->id,
            'content' => "Shoutout {$number}",
            'is_approved' => true,
        ]);
    }

    actingAs($user)
        ->get(route('ngl.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('messages', 24)
            ->where('pagination.hasMore', true));

    $first = actingAs($user)
        ->getJson(route('ngl.feed'))
        ->assertOk()
        ->assertJsonCount(24, 'data');

    actingAs($user)
        ->getJson(route('ngl.feed', ['cursor' => $first->json('meta.nextCursor')]))
        ->assertOk()
        ->assertJsonCount(6, 'data')
        ->assertJsonPath('meta.hasMore', false);
});

it('cursor paginates XP history', function () {
    $user = User::factory()->create();

    foreach (range(1, 35) as $number) {
        DB::table('gamification_histories')->insert([
            'user_id' => $user->id,
            'amount_xp' => 1,
            'amount_points' => 0,
            'reason' => 'Activity',
            'description' => "Entry {$number}",
            'created_at' => now()->subMinutes(35 - $number),
            'updated_at' => now()->subMinutes(35 - $number),
        ]);
    }

    $first = actingAs($user)
        ->getJson(route('users.xp-history', ['user' => $user->public_id]))
        ->assertOk()
        ->assertJsonCount(30, 'data')
        ->assertJsonPath('meta.hasMore', true);

    actingAs($user)
        ->getJson(route('users.xp-history', [
            'user' => $user->public_id,
            'cursor' => $first->json('meta.nextCursor'),
        ]))
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('meta.hasMore', false);
});

it('bounds each leaderboard while preserving complete totals and the viewer rank', function () {
    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create();
    $user = User::factory()->create();
    $user->sections()->attach($section->id, ['season_id' => $season->id]);

    User::factory()->count(105)->create()->each(
        fn (User $peer) => $peer->sections()->attach($section->id, ['season_id' => $season->id])
    );

    $leaderboard = app(LeaderboardService::class)
        ->forUserSections($user, $season)[0];

    expect($leaderboard['users'])->toHaveCount(LeaderboardService::MAX_VISIBLE_USERS)
        ->and($leaderboard['totalPlayers'])->toBe(106)
        ->and($leaderboard['userRank'])->toBe(1)
        ->and($leaderboard['isTruncated'])->toBeTrue();
});

it('paginates lightweight exam cards and defers answer-heavy review data', function () {
    $user = User::factory()->create();

    Exam::factory()->count(25)->published()->create()->each(function (Exam $exam): void {
        ExamPart::factory()->forExam($exam)->multipleChoice()->create();
    });

    actingAs($user)
        ->get(route('exams.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('examsBySeason.0.exams', 24)
            ->where('examsBySeason.0.exams.0.parts.0.questions', [])
            ->where('examPagination.hasMore', true));

    $first = actingAs($user)
        ->getJson(route('exams.listing'))
        ->assertOk()
        ->assertJsonCount(24, 'data.0.exams');

    actingAs($user)
        ->getJson(route('exams.listing', ['cursor' => $first->json('meta.nextCursor')]))
        ->assertOk()
        ->assertJsonCount(1, 'data.0.exams')
        ->assertJsonPath('meta.hasMore', false);
});

it('loads full exam answers only from the authorized review endpoint', function () {
    $user = User::factory()->create();
    $exam = Exam::factory()->closed()->create();
    $part = ExamPart::factory()->forExam($exam)->multipleChoice(1)->create();

    DB::table('exam_submissions')->insert([
        'user_id' => $user->id,
        'exam_id' => $exam->id,
        'exam_part_id' => $part->id,
        'answers' => json_encode([['question_number' => 1, 'answer' => 1]]),
        'status' => 'submitted',
        'score' => 2,
        'is_late' => false,
        'grading_failed' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($user)
        ->getJson(route('exams.review', $exam))
        ->assertOk()
        ->assertJsonPath('exam.parts.0.questions.0.options.1.is_correct', true)
        ->assertJsonPath('submissions.0.answers.0.answer', 1);
});
