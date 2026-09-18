<?php

use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use App\Services\TeacherXpAwardService;
use Illuminate\Validation\ValidationException;

it('lets a teacher award xp without changing academic points', function () {
    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create();
    $student = User::factory()->create(['exp' => 0, 'points' => 12]);
    $teacher = User::factory()->superAdmin()->create();
    $student->sections()->attach($section->id, ['season_id' => $season->id]);
    $this->actingAs($teacher);

    app(TeacherXpAwardService::class)->award(
        $student,
        $section->id,
        20,
        'Excellent class participation',
    );

    $student->refresh();
    $history = $student->gamificationHistories()->latest('id')->firstOrFail();

    expect((float) $student->exp)->toBe(20.0)
        ->and((float) $student->points)->toBe(12.0)
        ->and($history->reason)->toBe('Teacher Award')
        ->and($history->description)->toBe('Excellent class participation')
        ->and((int) $history->awarded_by)->toBe($teacher->id)
        ->and((float) $history->amount_xp)->toBe(20.0)
        ->and((float) $history->amount_points)->toBe(0.0);
});

it('prevents teacher xp awards outside a students enrolled sections', function () {
    $student = User::factory()->create();
    $otherSection = Section::factory()->create();

    expect(fn () => app(TeacherXpAwardService::class)->award(
        $student,
        $otherSection->id,
        10,
        'Participation',
    ))->toThrow(ValidationException::class);
});
