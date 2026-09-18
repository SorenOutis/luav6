<?php

use App\Models\Grade;
use App\Models\Section;
use App\Models\User;

function recordGrade(User $student, User $admin, Section $section, string $period, float $score): void
{
    Grade::create([
        'user_id' => $student->id,
        'section_id' => $section->id,
        'subject' => $section->name,
        'period' => $period,
        'score' => $score,
        'max_score' => 100,
        'recorded_by' => $admin->id,
    ]);
}

it('marks partial grades as current rather than final', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $section = Section::factory()->create([
        'name' => 'Mathematics',
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
    ]);
    $student = User::factory()->create();
    $student->sections()->attach($section->id);

    recordGrade($student, $admin, $section, 'Prelim', 90);

    $this->actingAs($student)
        ->getJson(route('api.grades'))
        ->assertOk()
        ->assertJsonPath('subjectGrades.0.currentAverage', 90)
        ->assertJsonPath('subjectGrades.0.semesterGrade', null)
        ->assertJsonPath('subjectGrades.0.gradedPeriods', 1)
        ->assertJsonPath('subjectGrades.0.totalPeriods', 3)
        ->assertJsonPath('subjectGrades.0.isComplete', false);
});

it('publishes a final grade only when every required period is graded', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $section = Section::factory()->create([
        'name' => 'Science',
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
    ]);
    $student = User::factory()->create();
    $student->sections()->attach($section->id);

    recordGrade($student, $admin, $section, 'Prelim', 90);
    recordGrade($student, $admin, $section, 'Midterm', 80);
    recordGrade($student, $admin, $section, 'Final', 85);

    $this->actingAs($student)
        ->getJson(route('api.grades'))
        ->assertOk()
        ->assertJsonPath('subjectGrades.0.currentAverage', 85)
        ->assertJsonPath('subjectGrades.0.semesterGrade', 85)
        ->assertJsonPath('subjectGrades.0.isComplete', true);
});

it('keeps sections with duplicate display names as separate grade rows', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $firstSection = Section::factory()->create([
        'name' => 'Special Topics',
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
    ]);
    $secondSection = Section::factory()->create([
        'name' => 'Special Topics',
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
    ]);
    $student = User::factory()->create();
    $student->sections()->attach([$firstSection->id, $secondSection->id]);

    $this->actingAs($student)
        ->getJson(route('api.grades'))
        ->assertOk()
        ->assertJsonCount(2, 'subjectGrades');
});
