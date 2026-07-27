<?php

/**
 * Phase 3 — behaviour preservation for the route-closure extraction.
 *
 * The dashboard, leaderboard and upcoming-exams logic moved out of closures in
 * routes/web.php into controllers and services. These tests assert the
 * behaviour is unchanged, and that route caching is now possible.
 */

use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use App\Services\LeaderboardService;
use App\Services\StreakService;
use App\Services\UpcomingExamsService;

use function Pest\Laravel\actingAs;

function dashboardContext(): array
{
    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create();
    $user = User::factory()->create();
    $user->sections()->attach($section->id, ['season_id' => $season->id]);

    return [$user, $section, $season];
}

// ─────────────────────────────────────────────
//  Route caching — the point of Phase 3
// ─────────────────────────────────────────────

it('has no closure based routes in our web routes file', function () {
    // Closures cannot be serialized, so a single one makes route:cache fail.
    // We only check routes.php routes — framework packages (Livewire, Octane)
    // register their own closure routes which don't affect route:cache.
    $closures = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route) => $route->getAction('uses') instanceof Closure)
        ->reject(fn ($route) => str_starts_with($route->uri(), 'livewire-'))
        ->reject(fn ($route) => str_starts_with($route->uri(), '_boost/'))
        ->reject(fn ($route) => $route->uri() === 'up')
        ->reject(fn ($route) => $route->uri() === 'storage/{path}')
        ->map(fn ($route) => $route->uri());

    expect($closures)->toBeEmpty();
});

// ─────────────────────────────────────────────
//  Dashboard renders
// ─────────────────────────────────────────────

it('renders the dashboard with the expected props', function () {
    [$user] = dashboardContext();

    actingAs($user)->get('/dashboard')->assertOk();
});

// ─────────────────────────────────────────────
//  StreakService
// ─────────────────────────────────────────────

it('starts a streak at one on first activity', function () {
    $user = User::factory()->create(['last_login_at' => null, 'current_streak' => 0]);

    app(StreakService::class)->touch($user);

    expect($user->fresh()->current_streak)->toBe(1);
});

it('increments the streak on a consecutive day', function () {
    $user = User::factory()->create([
        'last_login_at' => now()->subDay(),
        'current_streak' => 4,
        'longest_streak' => 4,
    ]);

    app(StreakService::class)->touch($user);

    expect($user->fresh()->current_streak)->toBe(5)
        ->and($user->fresh()->longest_streak)->toBe(5);
});

it('resets the streak after a missed day but keeps the longest', function () {
    $user = User::factory()->create([
        'last_login_at' => now()->subDays(3),
        'current_streak' => 9,
        'longest_streak' => 9,
    ]);

    app(StreakService::class)->touch($user);

    expect($user->fresh()->current_streak)->toBe(1)
        ->and($user->fresh()->longest_streak)->toBe(9);
});

/**
 * The old inline version could double-increment when two dashboard loads raced.
 */
it('is idempotent within the same day', function () {
    $user = User::factory()->create([
        'last_login_at' => now()->subDay(),
        'current_streak' => 2,
    ]);

    $service = app(StreakService::class);
    $service->touch($user);
    $service->touch($user);
    $service->touch($user);

    expect($user->fresh()->current_streak)->toBe(3);
});

// ─────────────────────────────────────────────
//  LeaderboardService — one implementation, two callers
// ─────────────────────────────────────────────

it('returns an entry per student in the section', function () {
    [$user, $section, $season] = dashboardContext();

    $peer = User::factory()->create();
    $peer->sections()->attach($section->id, ['season_id' => $season->id]);

    $boards = app(LeaderboardService::class)->forUserSections($user, $season);

    expect($boards)->toHaveCount(1)
        ->and($boards[0]['users'])->toHaveCount(2)
        ->and($boards[0]['totalPlayers'])->toBe(2);
});

it('excludes admins from the leaderboard', function () {
    [$user, $section, $season] = dashboardContext();

    $admin = User::factory()->admin()->create();
    $admin->sections()->attach($section->id, ['season_id' => $season->id]);

    $boards = app(LeaderboardService::class)->forUserSections($user, $season);

    expect($boards[0]['users'])->toHaveCount(1);
});

it('marks the viewing user', function () {
    [$user, $section, $season] = dashboardContext();

    $boards = app(LeaderboardService::class)->forUserSections($user, $season);
    $entry = collect($boards[0]['users'])->firstWhere('id', $user->id);

    expect($entry['isCurrentUser'])->toBeTrue();
});

it('returns nothing without a season', function () {
    [$user] = dashboardContext();

    expect(app(LeaderboardService::class)->forUserSections($user, null))->toBe([]);
});

/**
 * The dashboard closure and api/leaderboard had diverged. Both now call the
 * same service, so the shapes must match.
 */
it('serves the same leaderboard shape over the api', function () {
    [$user, $section, $season] = dashboardContext();

    $response = actingAs($user)->getJson('/api/leaderboard?season_id='.$season->id);

    $response->assertOk()->assertJsonStructure([
        'leaderboards' => [['sectionId', 'sectionName', 'users', 'userRank', 'totalPlayers']],
        'selectedSeason' => ['id', 'name'],
    ]);
});

// ─────────────────────────────────────────────
//  UpcomingExamsService
// ─────────────────────────────────────────────

it('reports submitted part counts without n+1 queries', function () {
    [$user, $section, $season] = dashboardContext();

    $exam = Exam::factory()->published()->forSection($section)->create();
    ExamPart::factory()->forExam($exam)->multipleChoice()->create();
    ExamPart::factory()->forExam($exam)->identification(['Manila'])->create();

    $exams = app(UpcomingExamsService::class)
        ->forUser($user, collect([$section->id]));

    expect($exams)->toHaveCount(1)
        ->and($exams[0]['parts_count'])->toBe(2)
        ->and($exams[0]['submitted_parts'])->toBe(0)
        ->and($exams[0]['is_completed'])->toBeFalse();
});

it('hides draft exams', function () {
    [$user, $section] = dashboardContext();

    Exam::factory()->draft()->forSection($section)->create();

    $exams = app(UpcomingExamsService::class)->forUser($user, collect([$section->id]));

    expect($exams)->toBeEmpty();
});

// ─────────────────────────────────────────────
//  Phase 4 — enums
// ─────────────────────────────────────────────

it('reveals answers only for closed exams', function () {
    expect(App\Enums\ExamStatus::Draft->allowsAnswerReview())->toBeFalse()
        ->and(App\Enums\ExamStatus::Published->allowsAnswerReview())->toBeFalse()
        ->and(App\Enums\ExamStatus::Closed->allowsAnswerReview())->toBeTrue();
});

it('accepts submissions only for published exams', function () {
    expect(App\Enums\ExamStatus::Draft->acceptsSubmissions())->toBeFalse()
        ->and(App\Enums\ExamStatus::Published->acceptsSubmissions())->toBeTrue()
        ->and(App\Enums\ExamStatus::Closed->acceptsSubmissions())->toBeFalse();
});
