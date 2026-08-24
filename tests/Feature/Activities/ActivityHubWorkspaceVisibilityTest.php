<?php

use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

/**
 * Regression: the Activities Hub hid every exam from students whose section
 * membership was not mirrored by workspace bookkeeping (`workspace_user` +
 * `current_workspace_id`). Enrollment — not tenant bookkeeping — is the
 * source of truth for student visibility.
 */
beforeEach(function () {
    // Super admin + their tenant, exactly like production: the account
    // auto-creates its owner workspace, and the season/section/exam are
    // stamped with that workspace while the super admin creates them.
    $this->superAdmin = User::factory()->create([
        'is_admin' => true,
        'is_super_admin' => true,
    ]);
    $this->workspace = $this->superAdmin->currentWorkspace;

    actingAs($this->superAdmin);

    $this->season = Season::factory()->create();
    $this->section = Section::factory()->forSeason($this->season)->create();
    $this->exam = Exam::factory()->published()->forSection($this->section)->create();

    ExamPart::factory()->forExam($this->exam)->multipleChoice(1)->create();

    auth()->logout();
});

/** Enroll a student via a raw pivot row, skipping all workspace bookkeeping. */
function enrollWithoutWorkspaceLinkage(User $student, Section $section, ?Season $season = null): void
{
    DB::table('section_user')->insert([
        'user_id' => $student->id,
        'section_id' => $section->id,
        'season_id' => $season?->id ?? $section->season_id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('shows super admin workspace exams to enrolled students whose membership lacks workspace linkage', function () {
    expect((int) $this->exam->workspace_id)->toBe((int) $this->workspace->id)
        ->and((int) $this->section->workspace_id)->toBe((int) $this->workspace->id);

    $student = User::factory()->create();
    enrollWithoutWorkspaceLinkage($student, $this->section, $this->season);

    // No workspace membership, no current workspace — the exact broken state.
    expect($student->workspaces()->exists())->toBeFalse()
        ->and($student->current_workspace_id)->toBeNull();

    actingAs($student)
        ->get(route('activities.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Activities/Index')
            ->has('examsBySeason.0.exams', 1)
            ->where('examsBySeason.0.exams.0.id', $this->exam->id)
            ->where('examsBySeason.0.exams.0.section_name', $this->section->name)
            ->where('examsBySeason.0.seasonName', $this->season->name)
            ->where('hubStats.exams.total', 1)
            ->where('sectionTabs.0.key', 'all'));

    // The listing endpoint used by "Load more" / polling agrees.
    actingAs($student)
        ->getJson(route('activities.listing'))
        ->assertOk()
        ->assertJsonCount(1, 'data.0.exams');
});

it('lets enrolled students open the exam even without workspace linkage', function () {
    $student = User::factory()->create();
    enrollWithoutWorkspaceLinkage($student, $this->section, $this->season);

    actingAs($student)
        ->get(route('exams.show', ['exam' => $this->exam->id]))
        ->assertOk();
});

it('keeps other tenants exams hidden while showing global ones', function () {
    $otherWorkspace = Workspace::factory()->create();
    $otherSection = Section::factory()
        ->forSeason($this->season)
        ->create(['workspace_id' => $otherWorkspace->id]);
    $foreignSectionExam = Exam::factory()->published()
        ->forSection($otherSection)
        ->create(['workspace_id' => $otherWorkspace->id]);
    $foreignGlobalExam = Exam::factory()->published()
        ->create(['workspace_id' => $otherWorkspace->id]);

    // Global exams the student's own tenant owns (or that predate tenants).
    $ownGlobalExam = Exam::factory()->published()
        ->create(['workspace_id' => $this->workspace->id]);
    $legacyGlobalExam = Exam::factory()->published()->create();

    $student = User::factory()->create();
    enrollWithoutWorkspaceLinkage($student, $this->section, $this->season);

    actingAs($student)
        ->get(route('activities.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            // One group for the season-targeted exam, one "Other" group for
            // the two unassigned global exams.
            ->has('examsBySeason', 2)
            ->where('hubStats.exams.total', 3));

    $visibleIds = collect(
        actingAs($student)->getJson(route('activities.listing'))->json('data'),
    )
        ->flatMap(fn ($group) => collect($group['exams'])->pluck('id'))
        ->all();

    expect($visibleIds)
        ->toContain($this->exam->id)
        ->toContain($ownGlobalExam->id)
        ->toContain($legacyGlobalExam->id)
        ->not->toContain($foreignSectionExam->id)
        ->not->toContain($foreignGlobalExam->id);

    // Opening a foreign tenant's exam resolves but is rejected at the
    // enrollment gate — students only reach exams their sections own.
    actingAs($student)
        ->get(route('exams.show', ['exam' => $foreignSectionExam->id]))
        ->assertForbidden();
});

it('shows exams from every workspace a multi-workspace student is enrolled in', function () {
    $secondWorkspace = Workspace::factory()->create();
    $secondSection = Section::factory()
        ->forSeason($this->season)
        ->create(['workspace_id' => $secondWorkspace->id]);
    $secondExam = Exam::factory()->published()
        ->forSection($secondSection)
        ->create(['workspace_id' => $secondWorkspace->id]);

    $student = User::factory()->create();
    enrollWithoutWorkspaceLinkage($student, $this->section, $this->season);
    enrollWithoutWorkspaceLinkage($student, $secondSection, $this->season);

    // Bookkeeping exists but points at only ONE of the two tenants.
    $student->workspaces()->attach($this->workspace->id, ['role' => 'student']);
    $student->workspaces()->attach($secondWorkspace->id, ['role' => 'student']);
    $student->forceFill(['current_workspace_id' => $secondWorkspace->id])->save();

    actingAs($student)
        ->getJson(route('activities.listing'))
        ->assertOk()
        ->assertJsonCount(2, 'data.0.exams');

    $visibleIds = collect(
        actingAs($student)->getJson(route('activities.listing'))->json('data'),
    )
        ->flatMap(fn ($group) => collect($group['exams'])->pluck('id'))
        ->all();

    expect($visibleIds)->toContain($this->exam->id)->toContain($secondExam->id);
});

it('still shows exams to students who joined through the normal section flow', function () {
    $student = User::factory()->create();

    // Regular Eloquent attach fires the SectionUser hook, which syncs the
    // workspace membership and activates the tenant for the student.
    $student->sections()->attach($this->section->id, ['season_id' => $this->season->id]);

    expect($student->workspaces()->whereKey($this->workspace->id)->exists())->toBeTrue();

    actingAs($student)
        ->get(route('activities.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('examsBySeason.0.exams', 1)
            ->where('examsBySeason.0.exams.0.id', $this->exam->id));
});

it('keeps the admin hub view scoped to their own workspace', function () {
    $otherWorkspace = Workspace::factory()->create();
    $otherSection = Section::factory()
        ->create(['workspace_id' => $otherWorkspace->id]);
    Exam::factory()->published()
        ->forSection($otherSection)
        ->create(['workspace_id' => $otherWorkspace->id]);

    // Workspace admin: factory auto-creates and activates their own tenant.
    $admin = User::factory()->create(['is_admin' => true]);
    expect((int) $admin->current_workspace_id)->not->toBe((int) $otherWorkspace->id);

    actingAs($admin)
        ->getJson(route('activities.listing'))
        ->assertOk()
        ->assertJsonPath('data', []);
});
