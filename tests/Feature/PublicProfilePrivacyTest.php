<?php

use App\Models\Season;
use App\Models\SeasonProgress;
use App\Models\Section;
use App\Models\User;
use App\Notifications\StudentActivityNotification;
use App\Notifications\UserFollowedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

function sharedProfileContext(array $profileOverrides = []): array
{
    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create();
    $viewer = User::factory()->create();
    $profile = User::factory()->create($profileOverrides);
    $viewer->sections()->attach($section->id, ['season_id' => $season->id]);
    $profile->sections()->attach($section->id, ['season_id' => $season->id]);

    return [$viewer, $profile, $section, $season];
}

it('generates unique UUIDv7 public profile identifiers', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();

    expect(Str::isUuid($first->public_id))->toBeTrue()
        ->and(Str::isUuid($second->public_id))->toBeTrue()
        ->and(explode('-', $first->public_id)[2][0])->toBe('7')
        ->and($first->public_id)->not->toBe($second->public_id);
});

it('uses UUIDs for profile routes and rejects sequential numeric identifiers', function () {
    [$viewer, $profile] = sharedProfileContext();

    actingAs($viewer)
        ->get(route('users.show', ['user' => $profile->public_id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('profileUser.id', $profile->public_id));

    actingAs($viewer)
        ->get('/u/'.$profile->id)
        ->assertNotFound();
});

it('allows same-section students but blocks unrelated and private profiles', function () {
    [$viewer, $profile] = sharedProfileContext();

    actingAs($viewer)
        ->get(route('users.show', ['user' => $profile->public_id]))
        ->assertOk();

    actingAs(User::factory()->create())
        ->get(route('users.show', ['user' => $profile->public_id]))
        ->assertForbidden();

    $profile->update(['profile_visibility' => User::PROFILE_VISIBILITY_PRIVATE]);

    actingAs($viewer)
        ->get(route('users.show', ['user' => $profile->public_id]))
        ->assertForbidden();
});

it('never exposes admin accounts as student-facing profiles', function () {
    $section = Section::factory()->create();
    $student = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $student->sections()->attach($section->id);
    $admin->sections()->attach($section->id);

    actingAs($student)
        ->get(route('users.show', ['user' => $admin->public_id]))
        ->assertForbidden();
});

it('lets a section owner view a private student profile', function () {
    $admin = User::factory()->admin()->create();
    $section = Section::factory()->create(['admin_id' => $admin->id]);
    $student = User::factory()->create([
        'profile_visibility' => User::PROFILE_VISIBILITY_PRIVATE,
    ]);
    $student->sections()->attach($section->id);

    actingAs($admin)
        ->get(route('users.show', ['user' => $student->public_id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canViewPrivateProgress', true)
            ->where('canViewActivity', true));
});

it('does not create progress or award badges while reading a profile', function () {
    [$viewer, $profile] = sharedProfileContext();

    expect(SeasonProgress::where('user_id', $profile->id)->count())->toBe(0);

    actingAs($viewer)
        ->get(route('users.show', ['user' => $profile->public_id]))
        ->assertOk();

    expect(SeasonProgress::where('user_id', $profile->id)->count())->toBe(0)
        ->and(DB::table('badge_user')->where('user_id', $profile->id)->count())->toBe(0);
});

it('shows peers only their shared section names', function () {
    [$viewer, $profile, $sharedSection, $season] = sharedProfileContext();
    $otherSection = Section::factory()->forSeason($season)->create();
    $profile->sections()->attach($otherSection->id, ['season_id' => $season->id]);

    actingAs($viewer)
        ->get(route('users.show', ['user' => $profile->public_id]))
        ->assertInertia(fn ($page) => $page
            ->has('profileUser.sections', 1)
            ->where('profileUser.sections.0', $sharedSection->name));
});

it('keeps detailed activity private unless the student opts in', function () {
    [$viewer, $profile] = sharedProfileContext();

    $profile->gamificationHistories()->create([
        'amount_xp' => 10,
        'amount_points' => 0,
        'reason' => 'Lesson Complete',
        'description' => 'Private detail',
    ]);

    actingAs($viewer)
        ->get(route('users.show', ['user' => $profile->public_id]))
        ->assertInertia(fn ($page) => $page
            ->where('canViewActivity', false)
            ->has('history', 0));

    actingAs($viewer)
        ->getJson(route('users.xp-history', ['user' => $profile->public_id]))
        ->assertForbidden();

    $profile->update(['profile_show_activity' => true]);

    actingAs($viewer)
        ->get(route('users.show', ['user' => $profile->public_id]))
        ->assertInertia(fn ($page) => $page
            ->where('canViewActivity', true)
            ->has('history', 1));

    actingAs($viewer)
        ->getJson(route('users.xp-history', ['user' => $profile->public_id]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('uses privacy-aware UUID routes for follows kudos and notifications', function () {
    Notification::fake();
    [$viewer, $profile] = sharedProfileContext();

    $profile->update(['profile_show_social' => false]);

    actingAs($viewer)
        ->post(route('users.follow', ['user' => $profile->public_id]))
        ->assertForbidden();
    actingAs($viewer)
        ->post(route('users.kudos', ['user' => $profile->public_id]), ['type' => 'great-work'])
        ->assertForbidden();

    $profile->update(['profile_show_social' => true]);

    actingAs($viewer)
        ->post(route('users.follow', ['user' => $profile->public_id]))
        ->assertRedirect();
    actingAs($viewer)
        ->post(route('users.kudos', ['user' => $profile->public_id]), ['type' => 'great-work'])
        ->assertRedirect();

    expect(DB::table('user_follows')
        ->where('follower_id', $viewer->id)
        ->where('followed_id', $profile->id)
        ->exists())->toBeTrue();

    Notification::assertSentTo(
        $profile,
        UserFollowedNotification::class,
        fn (UserFollowedNotification $notification): bool => $notification->toDatabase($profile)['href'] === "/u/{$viewer->public_id}",
    );
    Notification::assertSentTo(
        $profile,
        StudentActivityNotification::class,
        fn (StudentActivityNotification $notification): bool => $notification->toDatabase($profile)['href'] === "/u/{$viewer->public_id}",
    );
});
