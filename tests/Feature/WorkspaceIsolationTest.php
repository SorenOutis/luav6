<?php

use App\Models\Announcement;
use App\Models\Badge;
use App\Models\Course;
use App\Models\Exam;
use App\Models\Reward;
use App\Models\Section;
use App\Models\Setting;
use App\Models\User;

/**
 * ─────────────────────────────────────────────
 * Helpers
 * ─────────────────────────────────────────────
 */

/**
 * Create a section owned by a specific admin, bypassing any global scope.
 */
function createSectionForAdmin(User $admin, string $name, ?string $joinCode = null): Section
{
    return Section::withoutGlobalScope('workspace')->create([
        'name' => $name,
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
        'join_code' => $joinCode ?? strtoupper(substr(md5(uniqid()), 0, 8)),
        'admin_id' => $admin->id,
    ]);
}

/**
 * Create an exam owned by a specific admin.
 */
function createExamForAdmin(User $admin, array $overrides = []): Exam
{
    return Exam::withoutGlobalScope('workspace')->create(array_merge([
        'title' => fake()->sentence(),
        'exam_date' => now()->addDays(7),
        'admin_id' => $admin->id,
    ], $overrides));
}

/**
 * Create a course owned by a specific admin.
 */
function createCourseForAdmin(User $admin): Course
{
    return Course::withoutGlobalScope('workspace')->create([
        'name' => fake()->words(2, true),
        'admin_id' => $admin->id,
    ]);
}

/**
 * Create a badge owned by a specific admin.
 */
function createBadgeForAdmin(User $admin): Badge
{
    return Badge::withoutGlobalScope('workspace')->create([
        'name' => fake()->words(2, true),
        'admin_id' => $admin->id,
    ]);
}

/**
 * Create a reward owned by a specific admin.
 */
function createRewardForAdmin(User $admin): Reward
{
    return Reward::withoutGlobalScope('workspace')->create([
        'name' => fake()->words(2, true),
        'description' => fake()->sentence(),
        'points_cost' => 100,
        'admin_id' => $admin->id,
    ]);
}

/**
 * Create an announcement owned by a specific admin.
 */
function createAnnouncementForAdmin(User $admin): Announcement
{
    return Announcement::withoutGlobalScope('workspace')->create([
        'title' => fake()->sentence(),
        'description' => fake()->paragraph(),
        'is_active' => true,
        'admin_id' => $admin->id,
    ]);
}

beforeEach(function () {
    $this->admin1 = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);
    $this->admin2 = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);
    $this->superAdmin = User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
    $this->student = User::factory()->create(['is_admin' => false, 'is_super_admin' => false]);
});

// ─────────────────────────────────────────────
// BelongsToWorkspace Global Scope – Section
// ─────────────────────────────────────────────

test('super admin sees sections from all workspaces', function () {
    createSectionForAdmin($this->admin1, 'Admin1 Section', 'AAAABBBB');
    createSectionForAdmin($this->admin2, 'Admin2 Section', 'CCCCDDDD');

    $this->actingAs($this->superAdmin);

    expect(Section::count())->toBe(2);
});

test('regular admin only sees their own sections', function () {
    createSectionForAdmin($this->admin1, 'Admin1 Section', 'AAAABBBB');
    createSectionForAdmin($this->admin2, 'Admin2 Section', 'CCCCDDDD');

    $this->actingAs($this->admin1);

    expect(Section::count())->toBe(1);
    expect(Section::first()->name)->toBe('Admin1 Section');
});

test('unauthenticated requests are not scoped', function () {
    createSectionForAdmin($this->admin1, 'Admin1 Section', 'AAAABBBB');
    createSectionForAdmin($this->admin2, 'Admin2 Section', 'CCCCDDDD');

    expect(Section::count())->toBe(2);
});

test('student is not affected by workspace scope', function () {
    createSectionForAdmin($this->admin1, 'Admin1 Section', 'AAAABBBB');
    createSectionForAdmin($this->admin2, 'Admin2 Section', 'CCCCDDDD');

    $this->actingAs($this->student);

    expect(Section::count())->toBe(2);
});

// ─────────────────────────────────────────────
// Auto-set admin_id on creation
// ─────────────────────────────────────────────

test('regular admin auto-sets admin_id when creating a section', function () {
    $this->actingAs($this->admin1);

    $section = Section::create([
        'name' => 'My Section',
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
        'join_code' => 'TEST1234',
    ]);

    expect($section->admin_id)->toBe($this->admin1->id);
});

test('super admin does NOT auto-set admin_id when creating a section', function () {
    $this->actingAs($this->superAdmin);

    $section = Section::create([
        'name' => 'My Section',
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
        'join_code' => 'TEST1234',
    ]);

    expect($section->admin_id)->toBeNull();
});

test('student does NOT auto-set admin_id when creating a section', function () {
    $this->actingAs($this->student);

    $section = Section::create([
        'name' => 'My Section',
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
        'join_code' => 'TEST1234',
    ]);

    expect($section->admin_id)->toBeNull();
});

test('admin_id set explicitly is not overwritten', function () {
    $this->actingAs($this->admin1);

    $section = Section::create([
        'name' => 'My Section',
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
        'join_code' => 'TEST1234',
        'admin_id' => $this->admin2->id,
    ]);

    // Should respect explicit admin_id, not auto-set to admin1
    expect($section->admin_id)->toBe($this->admin2->id);
});

// ─────────────────────────────────────────────
// Admin data isolation across all model types
// ─────────────────────────────────────────────

test('admin cannot see another admins sections', function () {
    createSectionForAdmin($this->admin1, 'S1');
    createSectionForAdmin($this->admin2, 'S2');

    $this->actingAs($this->admin1);
    $names = Section::pluck('name')->toArray();

    expect($names)->toBe(['S1']);
});

test('admin cannot see another admins exams', function () {
    createExamForAdmin($this->admin1);
    createExamForAdmin($this->admin2);

    $this->actingAs($this->admin1);

    expect(Exam::count())->toBe(1);
});

test('admin cannot see another admins courses', function () {
    createCourseForAdmin($this->admin1);
    createCourseForAdmin($this->admin2);

    $this->actingAs($this->admin1);

    expect(Course::count())->toBe(1);
});

test('admin cannot see another admins badges', function () {
    createBadgeForAdmin($this->admin1);
    createBadgeForAdmin($this->admin2);

    $this->actingAs($this->admin1);

    expect(Badge::count())->toBe(1);
});

test('admin cannot see another admins rewards', function () {
    createRewardForAdmin($this->admin1);
    createRewardForAdmin($this->admin2);

    $this->actingAs($this->admin1);

    expect(Reward::count())->toBe(1);
});

test('admin cannot see another admins announcements', function () {
    createAnnouncementForAdmin($this->admin1);
    createAnnouncementForAdmin($this->admin2);

    $this->actingAs($this->admin1);

    expect(Announcement::count())->toBe(1);
});

// ─────────────────────────────────────────────
// User::scopeForWorkspace()
// ─────────────────────────────────────────────

test('scopeForWorkspace lets super admin see all non-admin users', function () {
    // admin2 is also an admin — should be visible to super admin since
    // scopeForWorkspace only filters non-admin users
    $this->actingAs($this->superAdmin);

    $users = User::where('is_admin', false)->forWorkspace()->get();

    // Should see only the student (admin2 is admin, so filtered by where(is_admin, false))
    expect($users)->toHaveCount(1);
    expect($users->first()->id)->toBe($this->student->id);
});

test('scopeForWorkspace scopes regular admin to students in their sections', function () {
    $section = createSectionForAdmin($this->admin1, 'A1 Section', 'A1CODEX1');

    // Enroll student in admin1's section
    $this->student->sections()->attach($section->id, ['season_id' => $section->season_id]);

    $this->actingAs($this->admin1);

    $students = User::where('is_admin', false)->forWorkspace()->get();

    expect($students)->toHaveCount(1);
    expect($students->first()->id)->toBe($this->student->id);
});

test('scopeForWorkspace excludes students not enrolled in admins sections', function () {
    $section = createSectionForAdmin($this->admin1, 'A1 Section', 'A1CODEX1');

    // Enroll student in admin1's section (so they appear)
    $this->student->sections()->attach($section->id, ['season_id' => $section->season_id]);

    // Create another student enrolled nowhere
    $otherStudent = User::factory()->create(['is_admin' => false]);

    $this->actingAs($this->admin1);

    $students = User::where('is_admin', false)->forWorkspace()->get();

    expect($students)->toHaveCount(1);
    expect($students->first()->id)->toBe($this->student->id);
});

test('scopeForWorkspace does not apply to student users', function () {
    createSectionForAdmin($this->admin1, 'A1 Section', 'A1CODEX1');
    $section = Section::withoutGlobalScope('workspace')->first();
    $this->student->sections()->attach($section->id, ['season_id' => $section->season_id]);

    $this->actingAs($this->student);

    // Student sees all non-admin users (scope doesn't apply)
    $users = User::where('is_admin', false)->forWorkspace()->get();

    expect($users)->toHaveCount(1); // themselves only since is_admin = false
});

// ─────────────────────────────────────────────
// Join code bypasses workspace scope
// ─────────────────────────────────────────────

test('findByJoinCode finds sections across all workspaces', function () {
    $section1 = createSectionForAdmin($this->admin1, 'A1', 'AAAABBBB');
    createSectionForAdmin($this->admin2, 'A2', 'CCCCDDDD');

    // Looking up admin2's join code while acting as admin1
    $this->actingAs($this->admin1);

    $found = Section::findByJoinCode('CCCCDDDD');

    expect($found)->not->toBeNull();
    expect($found->id)->not->toBe($section1->id);
    expect($found->name)->toBe('A2');
});

test('generateUniqueJoinCode checks global uniqueness', function () {
    // Admin1 creates a section with a known code
    createSectionForAdmin($this->admin1, 'A1', 'AAAABBBB');

    // Admin2 generates a code — should not collide with admin1's code
    // We can't easily assert the exact code, but we can verify the method
    // doesn't throw or return a duplicate by checking the query
    $this->actingAs($this->admin2);

    $code = Section::generateUniqueJoinCode();

    // The generated code should not exist in ANY workspace
    expect(Section::withoutGlobalScope('workspace')->where('join_code', $code)->exists())->toBeFalse();
});

// ─────────────────────────────────────────────
// Setting model – workspace-aware fallback
// ─────────────────────────────────────────────

test('admin gets their per-workspace setting when it exists', function () {
    Setting::set('site_name', 'Global Name');
    Setting::set('site_name', 'Admin1 Name'); // creates a workspace-level one...
    // Actually, when acting as admin1, set() creates workspace-level.
    // But we need to set up data carefully without scope getting in the way.

    // Set global setting
    Setting::unguarded(fn () => Setting::create(['key' => 'site_name', 'value' => 'Global Name', 'admin_id' => null]));

    // Set admin1-specific setting
    Setting::unguarded(fn () => Setting::create(['key' => 'site_name', 'value' => 'Admin1 Workspace', 'admin_id' => $this->admin1->id]));

    $this->actingAs($this->admin1);

    expect(Setting::get('site_name'))->toBe('Admin1 Workspace');
});

test('admin falls back to global setting when no workspace setting exists', function () {
    Setting::unguarded(fn () => Setting::create(['key' => 'site_name', 'value' => 'Global Name', 'admin_id' => null]));

    $this->actingAs($this->admin1);

    expect(Setting::get('site_name'))->toBe('Global Name');
});

test('super admin always gets the global setting', function () {
    Setting::unguarded(fn () => Setting::create(['key' => 'site_name', 'value' => 'Global Name', 'admin_id' => null]));
    Setting::unguarded(fn () => Setting::create(['key' => 'site_name', 'value' => 'Admin1 Workspace', 'admin_id' => $this->admin1->id]));

    $this->actingAs($this->superAdmin);

    expect(Setting::get('site_name'))->toBe('Global Name');
});

test('setting returns default when no value exists', function () {
    expect(Setting::get('nonexistent_key', 'fallback'))->toBe('fallback');
});

test('setting set for regular admin creates workspace-level entry', function () {
    $this->actingAs($this->admin1);

    Setting::set('theme', 'dark');

    $entry = Setting::withoutGlobalScope('workspace')
        ->where('key', 'theme')
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->admin_id)->toBe($this->admin1->id);
    expect($entry->value)->toBe('dark');
});

test('setting set for super admin creates global entry', function () {
    $this->actingAs($this->superAdmin);

    Setting::set('theme', 'dark');

    $entry = Setting::where('key', 'theme')->first();

    expect($entry)->not->toBeNull();
    expect($entry->admin_id)->toBeNull();
    expect($entry->value)->toBe('dark');
});

// ─────────────────────────────────────────────
// admin() relationship
// ─────────────────────────────────────────────

test('workspace record has admin relationship', function () {
    $section = createSectionForAdmin($this->admin1, 'Rel Test', 'RELTEST1');

    expect($section->admin)->not->toBeNull();
    expect($section->admin->id)->toBe($this->admin1->id);
});

test('admin relationship is null for global records', function () {
    $section = Section::withoutGlobalScope('workspace')->create([
        'name' => 'Global Section',
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
        'join_code' => 'GLOBAL01',
        'admin_id' => null,
    ]);

    expect($section->admin)->toBeNull();
});

// ─────────────────────────────────────────────
// isSuperAdmin helper
// ─────────────────────────────────────────────

test('isSuperAdmin returns true only for super admins', function () {
    expect($this->superAdmin->isSuperAdmin())->toBeTrue();
    expect($this->admin1->isSuperAdmin())->toBeFalse();
    expect($this->admin2->isSuperAdmin())->toBeFalse();
    expect($this->student->isSuperAdmin())->toBeFalse();
});
