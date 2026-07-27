<?php

/**
 * Phase 2.1 — Setting::get() caching.
 *
 * Settings were read uncached, 1–2 queries per lookup, across 75 call sites.
 * HandleInertiaRequests alone read 6 per request and AIService another 9 in its
 * constructor, so a page load could issue 15–30 needless queries against a
 * single-writer SQLite file.
 */

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Setting::flushAllCaches();
});

it('reads a global setting', function () {
    Setting::create(['key' => 'school_name', 'value' => 'Test Academy', 'admin_id' => null]);

    expect(Setting::get('school_name'))->toBe('Test Academy');
});

it('falls back to the default when the key is missing', function () {
    expect(Setting::get('does_not_exist', 'fallback'))->toBe('fallback');
});

it('loads every global setting in a single query', function () {
    Setting::create(['key' => 'a', 'value' => '1', 'admin_id' => null]);
    Setting::create(['key' => 'b', 'value' => '2', 'admin_id' => null]);
    Setting::create(['key' => 'c', 'value' => '3', 'admin_id' => null]);

    Setting::flushAllCaches();

    DB::enableQueryLog();

    Setting::get('a');
    Setting::get('b');
    Setting::get('c');
    Setting::get('a'); // repeat

    $queries = collect(DB::getQueryLog())
        ->filter(fn ($q) => str_contains($q['query'], 'settings'))
        ->count();

    DB::disableQueryLog();

    expect($queries)->toBe(1);
});

it('preserves the exact stored string type', function () {
    Setting::create(['key' => 'ollama_enabled', 'value' => '1', 'admin_id' => null]);

    // ChatController does a strict `=== '1'` comparison, so the cache must not
    // coerce this to a bool or int.
    expect(Setting::get('ollama_enabled'))->toBe('1')
        ->and(Setting::get('ollama_enabled') === '1')->toBeTrue();
});

it('busts the cache when a setting changes', function () {
    Setting::create(['key' => 'school_name', 'value' => 'Old Name', 'admin_id' => null]);

    expect(Setting::get('school_name'))->toBe('Old Name');

    Setting::set('school_name', 'New Name');

    expect(Setting::get('school_name'))->toBe('New Name');
});

it('busts the cache when a setting is deleted', function () {
    Setting::create(['key' => 'temp', 'value' => 'here', 'admin_id' => null]);

    expect(Setting::get('temp'))->toBe('here');

    Setting::where('key', 'temp')->first()->delete();

    expect(Setting::get('temp', 'gone'))->toBe('gone');
});

// ─────────────────────────────────────────────
//  Workspace scoping — the Octane leak risk
// ─────────────────────────────────────────────

it('prefers a workspace setting over the global one', function () {
    $admin = User::factory()->admin()->create();

    Setting::create(['key' => 'school_name', 'value' => 'Global School', 'admin_id' => null]);
    Setting::create(['key' => 'school_name', 'value' => 'Admin School', 'admin_id' => $admin->id]);

    actingAs($admin);

    expect(Setting::get('school_name'))->toBe('Admin School');
});

it('falls back to global when the workspace has no override', function () {
    $admin = User::factory()->admin()->create();

    Setting::create(['key' => 'school_name', 'value' => 'Global School', 'admin_id' => null]);

    actingAs($admin);

    expect(Setting::get('school_name'))->toBe('Global School');
});

/**
 * ⚠️ The regression this guards against: caching settings in a static property
 * would persist across requests under Octane and serve one admin's workspace
 * values to the next user on the same worker.
 */
it('does not leak one admins workspace settings to another', function () {
    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();

    Setting::create(['key' => 'school_name', 'value' => 'Global', 'admin_id' => null]);
    Setting::create(['key' => 'school_name', 'value' => 'School A', 'admin_id' => $adminA->id]);
    Setting::create(['key' => 'school_name', 'value' => 'School B', 'admin_id' => $adminB->id]);

    actingAs($adminA);
    expect(Setting::get('school_name'))->toBe('School A');

    actingAs($adminB);
    expect(Setting::get('school_name'))->toBe('School B');

    // A student sees the global value, never a workspace one.
    actingAs(User::factory()->create());
    expect(Setting::get('school_name'))->toBe('Global');
});

it('does not leak workspace settings to a super admin', function () {
    $admin = User::factory()->admin()->create();
    $superAdmin = User::factory()->superAdmin()->create();

    Setting::create(['key' => 'school_name', 'value' => 'Global', 'admin_id' => null]);
    Setting::create(['key' => 'school_name', 'value' => 'Workspace', 'admin_id' => $admin->id]);

    actingAs($superAdmin);

    expect(Setting::get('school_name'))->toBe('Global');
});

it('busts only the affected workspace cache', function () {
    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();

    Setting::create(['key' => 'k', 'value' => 'A1', 'admin_id' => $adminA->id]);
    Setting::create(['key' => 'k', 'value' => 'B1', 'admin_id' => $adminB->id]);

    actingAs($adminA);
    expect(Setting::get('k'))->toBe('A1');
    actingAs($adminB);
    expect(Setting::get('k'))->toBe('B1');

    // Change A's value; B must be unaffected.
    actingAs($adminA);
    Setting::set('k', 'A2');

    expect(Setting::get('k'))->toBe('A2');

    actingAs($adminB);
    expect(Setting::get('k'))->toBe('B1');
});
