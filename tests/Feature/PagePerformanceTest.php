<?php

/**
 * Performance regressions — the things that made navigation slow.
 *
 * These are guards, not micro-benchmarks. Each one pins a specific cause of
 * the "clicking a page is slow" report:
 *
 *  1. Hot tables had no index on the columns every page filters/joins on.
 *     `foreignId()->constrained()` creates a foreign KEY, not an INDEX — MySQL
 *     adds one implicitly, SQLite and Postgres do not, and this app runs both.
 *  2. `Season::current()` ran a fresh query 3–5 times per dashboard render.
 *  3. The shared Inertia props run on EVERY navigation, so their query count
 *     is the floor for every page in the app.
 */

use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use App\Services\LeaderboardService;
use App\Support\RequestCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

// ─────────────────────────────────────────────
//  1. Indexes exist on the hot query paths
// ─────────────────────────────────────────────

/**
 * @return array<int, string>
 */
function indexNamesFor(string $table): array
{
    return collect(Schema::getIndexes($table))
        ->pluck('name')
        ->filter()
        ->map(fn ($name) => strtolower((string) $name))
        ->values()
        ->all();
}

dataset('indexed tables', [
    // [table, index name]
    ['gamification_histories', 'gam_hist_user_created_idx'],
    ['gamification_histories', 'gam_hist_user_season_idx'],
    ['section_user', 'section_user_user_season_idx'],
    ['section_user', 'section_user_section_season_idx'],
    ['section_progress', 'section_progress_section_exp_idx'],
    ['course_user', 'course_user_user_idx'],
    ['assignment_user', 'assignment_user_user_idx'],
    ['exam_submissions', 'exam_subs_user_exam_idx'],
    ['exams', 'exams_status_date_idx'],
    ['notifications', 'notifications_notifiable_read_idx'],
]);

it('indexes the columns every page load queries', function (string $table, string $index) {
    expect(indexNamesFor($table))->toContain(strtolower($index));
})->with('indexed tables');

/**
 * The dashboard heatmap runs
 *   where user_id = ? and created_at >= ?
 * against gamification_histories, which grows fastest of any table (one row
 * per XP event). Without the composite index this is a full table scan on
 * every dashboard load.
 *
 * The plan format is driver-specific, so the assertion is too. The index
 * *presence* checks above are what protect PostgreSQL in production; this only
 * proves the planner actually chooses the index when given the real query.
 */
it('uses an index for the dashboard heatmap query', function () {
    $user = User::factory()->create();

    $plan = DB::select(
        'EXPLAIN QUERY PLAN SELECT DISTINCT DATE(created_at) FROM gamification_histories WHERE user_id = ? AND created_at >= ?',
        [$user->id, now()->subDays(90)]
    );

    $detail = strtolower(collect($plan)->pluck('detail')->join(' '));

    // Indexed access reports "SEARCH ... USING [COVERING] INDEX <name>".
    // A missing index reports "SCAN gamification_histories".
    expect($detail)->toContain('gam_hist_user_created_idx')
        ->and($detail)->toContain('search gamification_histories')
        ->and($detail)->not->toContain('scan gamification_histories');
})->skip(
    fn () => DB::connection()->getDriverName() !== 'sqlite',
    'Query plan assertion is SQLite-specific.'
);

/**
 * The leaderboard ranks students with
 *   ROW_NUMBER() OVER (PARTITION BY section_id ORDER BY exp DESC)
 * The (section_id, exp) index lets SQLite satisfy both the partition and the
 * ordering from the index instead of sorting the whole table per request.
 */
it('uses an index for the leaderboard rank query', function () {
    $plan = DB::select(
        'EXPLAIN QUERY PLAN SELECT section_id, user_id, exp FROM section_progress WHERE section_id IN (1,2) ORDER BY exp DESC'
    );

    $detail = strtolower(collect($plan)->pluck('detail')->join(' '));

    expect($detail)->toContain('section_progress_section_exp_idx')
        ->and($detail)->not->toContain('scan section_progress');
})->skip(
    fn () => DB::connection()->getDriverName() !== 'sqlite',
    'Query plan assertion is SQLite-specific.'
);

/**
 * PostgreSQL-only. A CREATE INDEX CONCURRENTLY build that is interrupted
 * (deploy timeout, cancelled query, server restart) leaves an INVALID index
 * behind: it occupies disk and slows writes, but the planner refuses to use
 * it — so the table silently goes back to sequential scans and the app gets
 * slow again with no obvious cause.
 *
 * Recovery is `DROP INDEX CONCURRENTLY <name>` then re-run `php artisan migrate`.
 */
it('has no invalid indexes left over from a concurrent build', function () {
    $invalid = collect(DB::select(
        'SELECT indexrelid::regclass AS name FROM pg_index WHERE NOT indisvalid'
    ))->pluck('name');

    expect($invalid)->toBeEmpty(
        'Invalid indexes found: '.$invalid->join(', ').
        ' — DROP INDEX CONCURRENTLY each, then re-run migrations.'
    );
})->skip(
    fn () => DB::connection()->getDriverName() !== 'pgsql',
    'pg_index check is PostgreSQL-specific.'
);

// ─────────────────────────────────────────────
//  2. Season::current() is memoized per request
// ─────────────────────────────────────────────

it('queries for the active season only once per request', function () {
    Season::factory()->active()->create();

    // Warm the memo, then count only what happens afterwards.
    Season::current();

    DB::enableQueryLog();

    Season::current();
    Season::current();
    Season::current();

    $queries = collect(DB::getQueryLog())
        ->filter(fn ($q) => str_contains(strtolower($q['query']), 'from "seasons"'))
        ->count();

    DB::disableQueryLog();

    expect($queries)->toBe(0);
});

it('returns the same active season instance within a request', function () {
    $season = Season::factory()->active()->create();

    expect(Season::current()->id)->toBe($season->id)
        ->and(Season::current()->id)->toBe($season->id);
});

it('memoizes the absence of an active season without re-querying', function () {
    Season::current();

    DB::enableQueryLog();
    Season::current();
    $queries = collect(DB::getQueryLog())
        ->filter(fn ($q) => str_contains(strtolower($q['query']), 'from "seasons"'))
        ->count();
    DB::disableQueryLog();

    expect(Season::current())->toBeNull()
        ->and($queries)->toBe(0);
});

/**
 * Invalidation must be surgical. Activating a season used to clear the WHOLE
 * RequestCache, which would silently discard every other memoized value in the
 * request and quietly reintroduce the queries this cache exists to remove.
 */
it('clears only the season keys when a season is saved', function () {
    $cache = app(RequestCache::class);

    $cache->remember('unrelated:value', fn () => 'keep me');
    Season::current();

    Season::factory()->active()->create();

    expect($cache->has('unrelated:value'))->toBeTrue()
        ->and($cache->has('season:current:global'))->toBeFalse();
});

it('re-reads the active season after one is saved', function () {
    expect(Season::current())->toBeNull();

    $season = Season::factory()->active()->create();

    // The saved() hook clears the memo, so this must not return the cached null.
    expect(Season::current()?->id)->toBe($season->id);
});

/**
 * ⚠️ The regression this guards against: memoizing in a `static` property would
 * survive between requests under Octane and serve one admin's workspace season
 * to the next user on the same worker. RequestCache is a scoped binding, so
 * rebinding it (what Octane does per request) must yield a clean store.
 */
it('does not leak the memoized season across requests', function () {
    $adminA = User::factory()->admin()->create();
    $seasonA = Season::withoutGlobalScope('workspace')->create([
        'name' => 'A', 'start_date' => now(), 'is_active' => true, 'admin_id' => $adminA->id,
    ]);

    actingAs($adminA);
    expect(Season::current()?->id)->toBe($seasonA->id);

    // Simulate Octane's per-request flush of scoped bindings.
    app()->forgetScopedInstances();

    actingAs(User::factory()->create());

    expect(app(RequestCache::class)->has("season:current:{$adminA->current_workspace_id}"))->toBeFalse();
});

// ─────────────────────────────────────────────
//  3. Shared props — the floor cost of every navigation
// ─────────────────────────────────────────────

/**
 * HandleInertiaRequests::share() runs on every single page visit, so anything
 * expensive in it is multiplied across the whole app. Settings are cached and
 * the notification list is capped at 8 rows with an explicit column list.
 */
it('does not issue more queries per navigation as data grows', function () {
    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create();
    $user = User::factory()->create();
    $user->sections()->attach($section->id, ['season_id' => $season->id]);

    actingAs($user);

    // Warm anything cached across requests (the settings map) so the counts
    // below compare like with like.
    $this->get('/leaderboard')->assertOk();

    DB::enableQueryLog();
    $this->get('/leaderboard')->assertOk();
    $baseline = count(DB::getQueryLog());
    DB::disableQueryLog();
    // disableQueryLog() only stops logging — it does not clear the log, so
    // reset it before the next measurement window or this count would include
    // the baseline's queries.
    DB::flushQueryLog();

    // Add peers and XP history — an N+1 would scale the query count with these.
    User::factory()->count(5)->create()->each(function ($peer) use ($section, $season) {
        $peer->sections()->attach($section->id, ['season_id' => $season->id]);

        DB::table('gamification_histories')->insert([
            'user_id' => $peer->id,
            'season_id' => $season->id,
            'amount_xp' => 10,
            'amount_points' => 0,
            'reason' => 'Exam Submission',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
    });

    DB::enableQueryLog();
    $this->get('/leaderboard')->assertOk();
    $withMoreData = count(DB::getQueryLog());
    DB::disableQueryLog();

    // A fixed count is brittle (it moves whenever a feature is added), so the
    // assertion is on the shape of the cost, not its absolute value: rendering
    // the page must not get more expensive just because there is more data.
    expect($withMoreData)->toBe($baseline);
});

// ─────────────────────────────────────────────
//  4. LeaderboardService — no per-section queries
// ─────────────────────────────────────────────

/**
 * The build loop used to run `$section->users()->with(...)->get()` per section
 * (plus another roster query to collect user ids for the weekly-XP lookup), so
 * cost grew with the number of sections a student is enrolled in. Rosters are
 * now eager-loaded once.
 */
it('does not query per section when building the leaderboard', function () {
    $season = Season::factory()->active()->create();
    $user = User::factory()->create();

    $sections = collect(range(1, 4))->map(function () use ($season, $user) {
        $section = Section::factory()->forSeason($season)->create();
        $user->sections()->attach($section->id, ['season_id' => $season->id]);
        User::factory()->count(2)->create()->each(
            fn ($peer) => $peer->sections()->attach($section->id, ['season_id' => $season->id])
        );

        return $section;
    });

    DB::enableQueryLog();
    app(LeaderboardService::class)->forUserSections($user->fresh(), $season);
    $fourSectionQueries = count(DB::getQueryLog());
    DB::disableQueryLog();
    // See the note above: disableQueryLog() does not clear the query log.
    DB::flushQueryLog();

    // Now the same thing with a single section.
    $soloUser = User::factory()->create();
    $soloUser->sections()->attach($sections->first()->id, ['season_id' => $season->id]);

    DB::enableQueryLog();
    app(LeaderboardService::class)->forUserSections($soloUser->fresh(), $season);
    $oneSectionQueries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Query count must be flat in the number of sections, not linear.
    expect($fourSectionQueries)->toBe($oneSectionQueries);
});

/**
 * ⚠️ The regression this guards against: sectionProgress is now eager-loaded
 * for every one of the viewer's sections at once. Reading it with ->first()
 * would attribute a student's XP from one section to another.
 */
it('reports each students xp against the correct section', function () {
    $season = Season::factory()->active()->create();
    $sectionA = Section::factory()->forSeason($season)->create();
    $sectionB = Section::factory()->forSeason($season)->create();

    $user = User::factory()->create();
    $user->sections()->attach($sectionA->id, ['season_id' => $season->id]);
    $user->sections()->attach($sectionB->id, ['season_id' => $season->id]);

    DB::table('section_progress')->insert([
        ['user_id' => $user->id, 'section_id' => $sectionA->id, 'exp' => 500, 'points' => 0, 'level' => 6],
        ['user_id' => $user->id, 'section_id' => $sectionB->id, 'exp' => 100, 'points' => 0, 'level' => 2],
    ]);

    $boards = collect(app(LeaderboardService::class)->forUserSections($user->fresh(), $season))
        ->keyBy('sectionId');

    $inA = collect($boards[$sectionA->id]['users'])->firstWhere('id', $user->id);
    $inB = collect($boards[$sectionB->id]['users'])->firstWhere('id', $user->id);

    expect($inA['xp'])->toBe(500.0)
        ->and($inB['xp'])->toBe(100.0);
});

it('does not select the notification body columns it never renders', function () {
    $user = User::factory()->create();

    // Give the user a notification so the shared prop actually queries the
    // table — otherwise this assertion would pass vacuously.
    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\Test',
        'notifiable_type' => $user::class,
        'notifiable_id' => $user->id,
        'data' => json_encode(['title' => 'Hi', 'message' => 'There']),
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($user);

    DB::enableQueryLog();
    $this->get('/dashboard')->assertOk();
    $queries = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();

    $notificationReads = $queries->filter(
        fn ($q) => str_contains($q, 'from "notifications"') && str_starts_with($q, 'select')
    );

    // The prop maps a fixed set of fields, so the list query must name its
    // columns rather than `select *` the TEXT `data` blob for every row.
    expect($notificationReads)->not->toBeEmpty()
        ->and($notificationReads->filter(fn ($q) => str_contains($q, 'select * from "notifications"')))
        ->toBeEmpty();
});
