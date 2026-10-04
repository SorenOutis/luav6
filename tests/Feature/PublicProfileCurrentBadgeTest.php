<?php

use App\Models\Badge;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;

use function Pest\Laravel\actingAs;

function currentBadgeProfileContext(array $profileOverrides = []): array
{
    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create();
    $viewer = User::factory()->create();
    $profile = User::factory()->create($profileOverrides);
    $viewer->sections()->attach($section->id, ['season_id' => $season->id]);
    $profile->sections()->attach($section->id, ['season_id' => $season->id]);

    return [$viewer, $profile, $section, $season];
}

it('exposes the highest-level earned badge as the current badge', function () {
    [$viewer, $profile, $section, $season] = currentBadgeProfileContext();

    $bronze = Badge::create(['name' => 'Bronze Starter', 'required_level' => 1]);
    $gold = Badge::create(['name' => 'Gold Achiever', 'required_level' => 5]);

    $profile->badges()->attach($bronze->id, ['season_id' => $season->id]);
    $profile->badges()->attach($gold->id, ['season_id' => $season->id]);

    actingAs($viewer)
        ->get(route('users.show', ['user' => $profile->public_id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('currentBadge.id', $gold->id)
            ->where('currentBadge.name', 'Gold Achiever')
            ->where('currentBadge.requiredLevel', 5)
            ->where('currentBadge.earned', true));
});

it('returns a null current badge when the student has earned nothing', function () {
    [$viewer, $profile] = currentBadgeProfileContext();

    Badge::create(['name' => 'Un earned', 'required_level' => 2]);

    actingAs($viewer)
        ->get(route('users.show', ['user' => $profile->public_id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('currentBadge', null));
});

it('hides the current badge when achievements are private', function () {
    [$viewer, $profile, $section, $season] = currentBadgeProfileContext([
        'profile_show_achievements' => false,
    ]);

    $badge = Badge::create(['name' => 'Hidden Gem', 'required_level' => 3]);
    $profile->badges()->attach($badge->id, ['season_id' => $season->id]);

    actingAs($viewer)
        ->get(route('users.show', ['user' => $profile->public_id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canViewAchievements', false)
            ->where('currentBadge', null)
            ->where('stats.badgesCount', 0));
});
