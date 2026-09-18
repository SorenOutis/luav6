<?php

use App\Models\Announcement;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shows global announcements to every student', function () {
    Announcement::create([
        'title' => 'School-wide notice',
        'description' => 'For everyone',
        'is_active' => true,
    ]);

    $student = User::factory()->create();

    $this->actingAs($student)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('announcements.0.title', 'School-wide notice')
            ->where('announcements.0.sectionName', null));
});

it('shows section-targeted announcements only to students in that section', function () {
    $season = Season::factory()->active()->create();
    $sectionA = Section::factory()->forSeason($season)->create(['name' => 'Alpha']);
    $sectionB = Section::factory()->forSeason($season)->create(['name' => 'Beta']);

    Announcement::create([
        'title' => 'Alpha only announcement',
        'description' => 'Just for Alpha',
        'is_active' => true,
        'section_id' => $sectionA->id,
    ]);

    Announcement::create([
        'title' => 'Global announcement',
        'is_active' => true,
    ]);

    $studentA = User::factory()->create();
    $studentA->sections()->attach($sectionA->id, ['season_id' => $season->id]);

    $studentB = User::factory()->create();
    $studentB->sections()->attach($sectionB->id, ['season_id' => $season->id]);

    // Student in section A sees both the targeted and the global announcement.
    $this->actingAs($studentA)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('announcements', fn ($announcements) => collect($announcements)->pluck('title')->contains('Alpha only announcement')
                && collect($announcements)->pluck('title')->contains('Global announcement')));

    // Student in section B only sees the global announcement.
    $this->actingAs($studentB)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('announcements', fn ($announcements) => ! collect($announcements)->pluck('title')->contains('Alpha only announcement')
                && collect($announcements)->pluck('title')->contains('Global announcement')));
});

it('exposes the section name with targeted announcements', function () {
    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create(['name' => 'Alpha']);

    Announcement::create([
        'title' => 'Alpha only announcement',
        'is_active' => true,
        'section_id' => $section->id,
    ]);

    $student = User::factory()->create();
    $student->sections()->attach($section->id, ['season_id' => $season->id]);

    $this->actingAs($student)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('announcements.0.title', 'Alpha only announcement')
            ->where('announcements.0.sectionName', 'Alpha'));
});
