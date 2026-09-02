<?php

/**
 * Blocking a student from an exam.
 *
 * A block is a visibility rule that sits on top of the exam's section
 * targeting and is independent of the exam's status and schedule: the blocked
 * student must not find the exam in any list, must not be able to open it by
 * URL, and must not be dealt a set — while their classmates are unaffected and
 * the teacher's own view (and the student's past submissions) stay intact.
 */

use App\Filament\Resources\Exams\Schemas\ExamForm;
use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\ExamSet;
use App\Models\ExamSetAssignment;
use App\Models\ExamSubmission;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use App\Services\CalendarEventService;
use App\Services\ExamBlockService;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/**
 * A published exam in a section with two enrolled students: `$blocked` is the
 * one the teacher will bar, `$classmate` proves the block is per-student and
 * not exam-wide.
 *
 * @return array{0: Exam, 1: ExamPart, 2: User, 3: User, 4: Section, 5: Season}
 */
function blockingContext(array $examAttributes = []): array
{
    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create();

    $blocked = User::factory()->create();
    $blocked->sections()->attach($section->id, ['season_id' => $season->id]);

    $classmate = User::factory()->create();
    $classmate->sections()->attach($section->id, ['season_id' => $season->id]);

    $exam = Exam::factory()->published()->forSection($section)->create($examAttributes);
    $part = ExamPart::factory()->forExam($exam)->identification(['Manila'])->create();

    return [$exam, $part, $blocked, $classmate, $section, $season];
}

it('hides a blocked exam from the activities hub', function () {
    [$exam, , $blocked] = blockingContext();
    $exam->blockedUsers()->attach($blocked->id);

    actingAs($blocked)
        ->get('/activities')
        ->assertInertia(fn (Assert $page) => $page
            ->where('examsBySeason', [])
            ->where('hubStats.exams.total', 0)
            ->etc()
        );
});

it('leaves the exam on the hub for a classmate of a blocked student', function () {
    [$exam, , $blocked, $classmate] = blockingContext();
    $exam->blockedUsers()->attach($blocked->id);

    actingAs($classmate)
        ->get('/activities')
        ->assertInertia(fn (Assert $page) => $page
            ->where('hubStats.exams.total', 1)
            ->where('examsBySeason.0.exams.0.id', $exam->id)
            ->etc()
        );
});

it('hides a blocked exam from the hub listing endpoint', function () {
    [$exam, , $blocked] = blockingContext();
    $exam->blockedUsers()->attach($blocked->id);

    actingAs($blocked)
        ->getJson('/api/activities')
        ->assertOk()
        ->assertJsonPath('data', []);
});

it('hides a blocked exam from the legacy exam list', function () {
    [$exam, , $blocked] = blockingContext();
    $exam->blockedUsers()->attach($blocked->id);

    actingAs($blocked)
        ->get('/exams')
        ->assertInertia(fn (Assert $page) => $page
            ->where('examsBySeason', [])
            ->etc()
        );

    actingAs($blocked)
        ->getJson('/api/exams')
        ->assertOk()
        ->assertJsonPath('data', []);
});

it('hides a blocked exam from the dashboard and the calendar', function () {
    [$exam, , $blocked, , , $season] = blockingContext();
    $exam->blockedUsers()->attach($blocked->id);

    actingAs($blocked)
        ->getJson(route('api.dashboard-exams', ['season_id' => $season->id]))
        ->assertOk()
        ->assertJsonPath('exams', []);

    $events = app(CalendarEventService::class)->forUser(
        $blocked,
        now()->startOfMonth()->subMonths(2),
        now()->endOfMonth()->addMonths(12),
    );

    expect(collect($events['events'])->where('type', 'exam')->pluck('id'))
        ->not->toContain($exam->id);
});

it('returns 404 when a blocked student opens the exam by URL', function () {
    [$exam, , $blocked] = blockingContext();
    $exam->blockedUsers()->attach($blocked->id);

    actingAs($blocked)
        ->get(route('exams.show', $exam))
        ->assertNotFound();
})->group('security');

it('does not deal a set to a blocked student', function () {
    [$exam, , $blocked, $classmate] = blockingContext();

    // Give the exam a real deck: opening it would normally deal a set and write
    // an exam_set_assignments row for the visitor.
    $set = ExamSet::factory()->forExam($exam)->titled('Set A')->create();
    ExamPart::factory()->forSet($set)->identification(['Manila'])->create();

    $exam->blockedUsers()->attach($blocked->id);

    actingAs($blocked)->get(route('exams.show', $exam))->assertNotFound();

    expect(ExamSetAssignment::where('user_id', $blocked->id)->count())->toBe(0);

    // The classmate still gets dealt a set, so the assertion above is about the
    // block and not about dealing being broken.
    actingAs($classmate)->get(route('exams.show', $exam))->assertOk();

    expect(ExamSetAssignment::where('user_id', $classmate->id)->count())->toBe(1);
});

it('rejects every write action for a blocked student', function () {
    [$exam, $part, $blocked] = blockingContext();
    $exam->blockedUsers()->attach($blocked->id);

    $answers = ['answers' => [['question_number' => 1, 'answer' => 'Manila']]];

    actingAs($blocked)
        ->postJson(route('exams.startPart', [$exam, $part]))
        ->assertNotFound();

    actingAs($blocked)
        ->putJson(route('exams.saveAnswers', [$exam, $part]), $answers)
        ->assertNotFound();

    actingAs($blocked)
        ->postJson(route('exams.submitPart', [$exam, $part]), $answers)
        ->assertNotFound();

    expect(ExamSubmission::where('user_id', $blocked->id)->count())->toBe(0);
})->group('security');

it('hides a blocked exam from review even after the student submitted and it closed', function () {
    [$exam, $part, $blocked] = blockingContext(['status' => 'closed']);
    ExamSubmission::factory()->forSubmission($blocked, $exam, $part)->create();
    $exam->blockedUsers()->attach($blocked->id);

    actingAs($blocked)
        ->get(route('exams.review', $exam))
        ->assertNotFound();

    // The submission itself survives the block — the teacher still has it.
    expect(ExamSubmission::where('user_id', $blocked->id)->count())->toBe(1);
})->group('security');

it('hides the exam in every state of its lifecycle', function (array $examAttributes) {
    [$exam, , $blocked, $classmate] = blockingContext($examAttributes);
    $exam->blockedUsers()->attach($blocked->id);

    actingAs($blocked)
        ->getJson('/api/activities')
        ->assertJsonPath('data', []);

    // Sanity check: without the block the exam is genuinely visible in this
    // state, so the test above cannot pass by accident.
    actingAs($classmate)
        ->getJson('/api/activities')
        ->assertJsonPath('data.0.exams.0.id', $exam->id);
})->with([
    'upcoming' => [['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]],
    'open' => [['starts_at' => now()->subHour(), 'ends_at' => now()->addDay()]],
    'ended' => [['starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()]],
    'closed' => [['status' => 'closed']],
]);

it('keeps the exam visible to an admin even when students are blocked', function () {
    $admin = User::factory()->admin()->create();
    actingAs($admin);

    [$exam, , $blocked] = blockingContext();
    $exam->blockedUsers()->attach($blocked->id);

    actingAs($admin)
        ->getJson('/api/activities')
        ->assertJsonPath('data.0.exams.0.id', $exam->id);

    actingAs($admin)
        ->get(route('exams.show', $exam))
        ->assertOk();
});

it('restores the exam once the student is unblocked', function () {
    [$exam, , $blocked] = blockingContext();
    $exam->blockedUsers()->attach($blocked->id);

    actingAs($blocked)->getJson('/api/activities')->assertJsonPath('data', []);

    $exam->blockedUsers()->detach($blocked->id);

    actingAs($blocked)
        ->getJson('/api/activities')
        ->assertJsonPath('data.0.exams.0.id', $exam->id);

    actingAs($blocked)
        ->get(route('exams.show', $exam))
        ->assertOk();
});

it('records who blocked a student and replaces the list on re-sync', function () {
    $admin = User::factory()->admin()->create();
    actingAs($admin);

    [$exam, , $blocked, $classmate] = blockingContext();
    $service = app(ExamBlockService::class);

    $service->sync($exam, [$blocked->id, $classmate->id]);

    expect($exam->blockedUsers()->pluck('users.id')->all())
        ->toEqualCanonicalizing([$blocked->id, $classmate->id]);

    expect((int) $exam->blocks()->where('user_id', $blocked->id)->value('blocked_by'))
        ->toBe($admin->id);

    $service->sync($exam, [$blocked->id]);

    expect($exam->blockedUsers()->pluck('users.id')->all())->toBe([$blocked->id]);
});

it('keeps the original blocker when the exam is saved again', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();
    actingAs($admin);

    [$exam, , $blocked] = blockingContext();
    $service = app(ExamBlockService::class);

    $service->sync($exam, [$blocked->id]);

    // Another teacher edits the exam and saves without touching the picker:
    // the block stays, and so does the record of who wrote it.
    actingAs($otherAdmin);
    $service->sync($exam, [$blocked->id]);

    expect((int) $exam->blocks()->where('user_id', $blocked->id)->value('blocked_by'))
        ->toBe($admin->id);
});

it('only offers students of the exam section as block options', function () {
    $admin = User::factory()->admin()->create();
    actingAs($admin);

    [$exam, , $blocked, , $section] = blockingContext();

    $other = User::factory()->create();
    $otherSection = Section::factory()->forSeason($exam->section->season)->create();
    $other->sections()->attach($otherSection->id, ['season_id' => $otherSection->season_id]);

    $options = app(ExamBlockService::class)->optionsFor($section->id);

    expect(array_keys($options))->toContain($blocked->id);
    expect(array_keys($options))->not->toContain($other->id);
    expect($options[$blocked->id])->toBe($blocked->name);
});

it('never wipes the block list when the picker is absent from the form payload', function () {
    $data = ['title' => 'Midterm'];

    expect(ExamForm::extractBlockedUserIds($data))->toBeNull();
    expect($data)->toBe(['title' => 'Midterm']);

    $data = ['title' => 'Midterm', 'blocked_user_ids' => ['3', 5, 5, 0]];

    expect(ExamForm::extractBlockedUserIds($data))->toBe([3, 5]);
    expect($data)->toBe(['title' => 'Midterm']);
});
