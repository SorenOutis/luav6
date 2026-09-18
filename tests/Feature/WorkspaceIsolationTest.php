<?php

use App\Models\AiUsageLog;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Badge;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Reward;
use App\Models\Section;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Support\Facades\Schema;

function workspaceSection(User $admin, string $name, ?string $joinCode = null): Section
{
    return Section::withoutGlobalScope('workspace')->create([
        'name' => $name,
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
        'join_code' => $joinCode ?? strtoupper(substr(md5(uniqid()), 0, 8)),
        'admin_id' => $admin->id,
    ]);
}

function workspaceRecord(string $model, User $admin, array $attributes = [])
{
    return $model::withoutGlobalScope('workspace')->create(array_merge(
        match ($model) {
            Exam::class => ['title' => fake()->sentence(), 'exam_date' => now()->addDay()],
            Assignment::class => ['title' => fake()->sentence()],
            Course::class => ['name' => fake()->words(2, true)],
            Badge::class => ['name' => fake()->words(2, true)],
            Reward::class => ['name' => fake()->word(), 'description' => fake()->sentence(), 'points_cost' => 100],
            Announcement::class => ['title' => fake()->sentence(), 'description' => fake()->sentence(), 'is_active' => true],
            default => [],
        },
        ['admin_id' => $admin->id],
        $attributes,
    ));
}

beforeEach(function () {
    $this->admin1 = User::factory()->admin()->create();
    $this->admin2 = User::factory()->admin()->create();
    $this->superAdmin = User::factory()->superAdmin()->create();
    $this->student = User::factory()->create();
    app(WorkspaceContext::class)->clear();
});

it('leaves the retiring game schema unchanged', function () {
    expect(Schema::hasColumn('td_maps', 'workspace_id'))->toBeFalse()
        ->and(Schema::hasColumn('td_enemies', 'workspace_id'))->toBeFalse()
        ->and(Schema::hasColumn('td_towers', 'workspace_id'))->toBeFalse()
        ->and(Schema::hasColumn('td_difficulties', 'workspace_id'))->toBeFalse();
});

it('creates a tenant workspace for every regular admin', function () {
    expect($this->admin1->workspaces)->toHaveCount(1)
        ->and($this->admin1->current_workspace_id)->not->toBeNull()
        ->and($this->admin1->workspaces->first()->pivot->role)->toBe(Workspace::ROLE_OWNER);
});

it('scopes regular admins by workspace rather than creator', function () {
    workspaceSection($this->admin1, 'Workspace A', 'AAAABBBB');
    workspaceSection($this->admin2, 'Workspace B', 'CCCCDDDD');

    $this->actingAs($this->admin1);

    expect(Section::pluck('name')->all())->toBe(['Workspace A']);
});

it('lets co-admins share all records in one tenant', function () {
    $workspace = $this->admin1->currentWorkspace;
    $workspace->users()->syncWithoutDetaching([
        $this->admin2->id => ['role' => Workspace::ROLE_ADMIN],
    ]);
    $this->admin2->forceFill(['current_workspace_id' => $workspace->id])->save();

    $section = workspaceSection($this->admin1, 'Shared Section', 'SHARED01');
    $this->actingAs($this->admin2);

    expect(Section::first()?->id)->toBe($section->id);
});

it('keeps creator metadata without using it as the tenant boundary', function () {
    $workspace = $this->admin1->currentWorkspace;
    $workspace->users()->syncWithoutDetaching([
        $this->admin2->id => ['role' => Workspace::ROLE_ADMIN],
    ]);
    $this->admin2->forceFill(['current_workspace_id' => $workspace->id])->save();
    $this->actingAs($this->admin2);

    $section = Section::create([
        'name' => 'Created by co-admin',
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
        'join_code' => 'COADMIN1',
    ]);

    expect($section->workspace_id)->toBe($workspace->id)
        ->and($section->admin_id)->toBe($this->admin2->id);
});

it('keeps tenant data when the creating co-admin is deleted', function () {
    $workspace = $this->admin1->currentWorkspace;
    $workspace->users()->syncWithoutDetaching([
        $this->admin2->id => ['role' => Workspace::ROLE_ADMIN],
    ]);
    $this->admin2->forceFill(['current_workspace_id' => $workspace->id])->save();
    $this->actingAs($this->admin2);
    $section = Section::create([
        'name' => 'Durable tenant data',
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
        'join_code' => 'DURABLE1',
    ]);

    $this->admin2->delete();

    $persisted = Section::withoutGlobalScope('workspace')->findOrFail($section->id);
    expect($persisted->workspace_id)->toBe($workspace->id)
        ->and($persisted->admin_id)->toBeNull();
});

it('scopes every core tenant model consistently', function (string $model) {
    workspaceRecord($model, $this->admin1);
    workspaceRecord($model, $this->admin2);

    $this->actingAs($this->admin1);

    expect($model::count())->toBe(1);
})->with([
    Exam::class,
    Assignment::class,
    Course::class,
    Badge::class,
    Reward::class,
    Announcement::class,
]);

it('propagates the active workspace into nested learning and analytics records', function () {
    $this->actingAs($this->admin1);
    $course = Course::create(['name' => 'Tenant Course']);
    $module = CourseModule::create(['course_id' => $course->id, 'title' => 'Module']);
    $lesson = Lesson::create(['course_module_id' => $module->id, 'title' => 'Lesson']);
    $section = Section::create([
        'name' => 'Tenant Section',
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
        'join_code' => 'NESTED01',
    ]);
    $grade = Grade::create([
        'user_id' => $this->student->id,
        'section_id' => $section->id,
        'subject' => 'Math',
        'period' => 'Prelim',
        'score' => 90,
        'max_score' => 100,
    ]);
    $usage = AiUsageLog::create([
        'date' => now()->toDateString(),
        'provider' => 'test',
        'model' => 'test',
        'source' => 'test',
        'input_tokens' => 1,
        'output_tokens' => 1,
        'neurons' => 1,
    ]);

    expect($course->workspace_id)->toBe($this->admin1->current_workspace_id)
        ->and($module->workspace_id)->toBe($course->workspace_id)
        ->and($lesson->workspace_id)->toBe($course->workspace_id)
        ->and($grade->workspace_id)->toBe($course->workspace_id)
        ->and($usage->workspace_id)->toBe($course->workspace_id);
});

it('gives super admins a separate active workspace for writes', function () {
    expect($this->superAdmin->current_workspace_id)->not->toBeNull()
        ->and($this->superAdmin->workspaces)->toHaveCount(1);

    $this->actingAs($this->superAdmin);
    $section = Section::create([
        'name' => 'Super workspace section',
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
        'join_code' => 'SUPEROWN',
    ]);

    expect($section->workspace_id)->toBe($this->superAdmin->current_workspace_id)
        ->and($section->admin_id)->toBe($this->superAdmin->id);

    $this->actingAs($this->admin1);
    expect(Section::whereKey($section->id)->exists())->toBeFalse();
});

it('lets super admins inspect all tenant records while writing to their active one', function () {
    workspaceSection($this->admin1, 'A', 'SUPER001');
    workspaceSection($this->admin2, 'B', 'SUPER002');

    $this->actingAs($this->superAdmin);

    expect(Section::count())->toBe(2);
});

it('supports isolated super-admin inspection without losing platform mode', function () {
    $sectionA = workspaceSection($this->admin1, 'A', 'INSPECT1');
    workspaceSection($this->admin2, 'B', 'INSPECT2');
    $this->actingAs($this->superAdmin);
    $context = app(WorkspaceContext::class);

    expect(Section::count())->toBe(2);

    $context->inspect($this->admin1->currentWorkspace);
    expect(Section::pluck('id')->all())->toBe([$sectionA->id]);

    $created = Section::create([
        'name' => 'Created while inspecting',
        'school_level' => Section::SCHOOL_LEVEL_COLLEGE,
        'join_code' => 'INSPECT3',
    ]);
    expect($created->workspace_id)->toBe($this->admin1->current_workspace_id);

    $context->stopInspecting();
    expect(Section::count())->toBe(3);
});

it('archives workspaces without deleting tenant data and supports restore', function () {
    $workspace = $this->admin1->currentWorkspace;
    $section = workspaceSection($this->admin1, 'Preserved', 'ARCHIVE1');

    $workspace->archive($this->superAdmin);

    expect(Section::withoutGlobalScope('workspace')->whereKey($section->id)->exists())->toBeTrue()
        ->and($workspace->fresh()->isArchived())->toBeTrue()
        ->and($this->admin1->fresh()->current_workspace_id)->toBeNull();

    $this->actingAs($this->admin1);
    expect(Section::whereKey($section->id)->exists())->toBeFalse();

    $workspace->restore();
    $this->admin1->fresh()->activateWorkspace($workspace->fresh());
    expect(Section::whereKey($section->id)->exists())->toBeTrue();
});

it('scopes students to their active workspace', function () {
    $sectionA = workspaceSection($this->admin1, 'A', 'STUDENT1');
    workspaceSection($this->admin2, 'B', 'STUDENT2');
    $this->student->sections()->attach($sectionA->id);
    $this->student->joinWorkspace((int) $sectionA->workspace_id);
    $this->actingAs($this->student);

    expect(Section::pluck('name')->all())->toBe(['A']);
});

it('allows a student to switch between joined tenants', function () {
    $sectionA = workspaceSection($this->admin1, 'A', 'SWITCH01');
    $sectionB = workspaceSection($this->admin2, 'B', 'SWITCH02');
    $this->student->sections()->attach([$sectionA->id, $sectionB->id]);
    $this->student->forceFill(['current_workspace_id' => $sectionA->workspace_id])->save();
    $this->actingAs($this->student);

    expect(Section::pluck('name')->all())->toBe(['A']);

    $this->post(route('workspaces.activate', ['workspace' => $this->admin2->currentWorkspace->public_id]))
        ->assertRedirect();

    expect(Section::pluck('name')->all())->toBe(['B']);
});

it('finds globally unique join codes across tenant scopes', function () {
    workspaceSection($this->admin1, 'A', 'JOIN0001');
    $expected = workspaceSection($this->admin2, 'B', 'JOIN0002');
    $this->actingAs($this->admin1);

    expect(Section::findByJoinCode('JOIN0002')?->id)->toBe($expected->id);
});

it('shares settings between co-admins and students in one workspace', function () {
    $workspace = $this->admin1->currentWorkspace;
    $workspace->users()->syncWithoutDetaching([
        $this->admin2->id => ['role' => Workspace::ROLE_ADMIN],
        $this->student->id => ['role' => Workspace::ROLE_STUDENT],
    ]);
    $this->admin2->forceFill(['current_workspace_id' => $workspace->id])->save();
    $this->student->forceFill(['current_workspace_id' => $workspace->id])->save();

    $this->actingAs($this->admin1);
    Setting::set('school_name', 'Shared Academy');

    $this->actingAs($this->admin2);
    expect(Setting::get('school_name'))->toBe('Shared Academy');

    $this->actingAs($this->student);
    expect(Setting::get('school_name'))->toBe('Shared Academy');
});

it('falls back to global settings when a tenant has no override', function () {
    Setting::create([
        'key' => 'support_email',
        'value' => 'global@example.test',
        'workspace_id' => null,
        'admin_id' => $this->superAdmin->id,
    ]);

    $this->actingAs($this->admin1);
    expect(Setting::get('support_email'))->toBe('global@example.test');
});

it('keeps settings isolated between tenants', function () {
    $this->actingAs($this->admin1);
    Setting::set('school_name', 'Tenant A');

    $this->actingAs($this->admin2);
    Setting::set('school_name', 'Tenant B');

    $this->actingAs($this->admin1);
    expect(Setting::get('school_name'))->toBe('Tenant A');

    $this->actingAs($this->admin2);
    expect(Setting::get('school_name'))->toBe('Tenant B');
});

it('scopes the admin student roster to the active tenant', function () {
    $sectionA = workspaceSection($this->admin1, 'A', 'ROSTER01');
    $sectionB = workspaceSection($this->admin2, 'B', 'ROSTER02');
    $studentA = User::factory()->create();
    $studentB = User::factory()->create();
    $studentA->sections()->attach($sectionA->id);
    $studentB->sections()->attach($sectionB->id);

    $this->actingAs($this->admin1);

    expect(User::query()->where('is_admin', false)->forWorkspace()->pluck('id')->all())
        ->toBe([$studentA->id]);
});

it('restores an explicit queue-style workspace context after callbacks', function () {
    $this->actingAs($this->admin1);
    $context = app(WorkspaceContext::class);

    expect($context->id())->toBe($this->admin1->current_workspace_id);

    $inside = $context->run(
        $this->admin2->current_workspace_id,
        fn (): ?int => $context->id(),
    );

    expect($inside)->toBe($this->admin2->current_workspace_id)
        ->and($context->id())->toBe($this->admin1->current_workspace_id);
});

it('restores a fresh tenant context between Octane requests', function () {
    $this->actingAs($this->admin1);
    expect(app(WorkspaceContext::class)->id())->toBe($this->admin1->current_workspace_id);

    app()->forgetScopedInstances();
    $this->actingAs($this->admin2);

    expect(app(WorkspaceContext::class)->id())->toBe($this->admin2->current_workspace_id);
});
