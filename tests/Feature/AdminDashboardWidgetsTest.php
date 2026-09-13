<?php

/**
 * Admin dashboard widgets render with production-like data.
 *
 * Regression coverage: the lazy admin widgets only execute their table
 * queries when they load (on scroll), so a broken widget never fails the
 * initial page render — it 500s the lazy request instead. These tests
 * mount every lazy widget with realistic data (sections, students with
 * varied activity, exams, scored and pending submissions) to catch
 * render-time failures before they reach production.
 */

use App\Filament\Widgets\ActivityFeedWidget;
use App\Filament\Widgets\AdminActivityTrendChart;
use App\Filament\Widgets\ExamPerformanceWidget;
use App\Filament\Widgets\NeuronUsageWidget;
use App\Filament\Widgets\QuickActionsWidget;
use App\Filament\Widgets\RecentSupportTicketsWidget;
use App\Filament\Widgets\SectionComparisonWidget;
use App\Filament\Widgets\StudentRiskWidget;
use App\Filament\Widgets\TopStudentsWidget;
use App\Models\Exam;
use App\Models\ExamSubmission;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use Livewire\Livewire;

function seedDashboardData(): User
{
    $season = Season::factory()->active()->create();
    $admin = User::factory()->admin()->create();

    foreach (range(1, 3) as $i) {
        $section = Section::factory()->forSeason($season)->create();

        // Active student with submissions.
        $active = User::factory()->create([
            'current_streak' => 8,
            'exp' => 500,
            'last_login_at' => now(),
        ]);
        $active->sections()->attach($section->id, ['season_id' => $season->id]);
        $active->seasonProgress()->create([
            'season_id' => $season->id,
            'exp' => 500,
            'level' => 5,
            'points' => 100,
        ]);

        // At-risk student: never logged in, no XP.
        $risky = User::factory()->create([
            'current_streak' => 0,
            'exp' => 0,
            'last_login_at' => null,
        ]);
        $risky->sections()->attach($section->id, ['season_id' => $season->id]);

        // Stale student: last login 30 days ago.
        $stale = User::factory()->create([
            'current_streak' => 0,
            'exp' => 10,
            'last_login_at' => now()->subDays(30),
        ]);
        $stale->sections()->attach($section->id, ['season_id' => $season->id]);
    }

    $exam = Exam::factory()->create(['status' => 'published']);

    foreach (User::where('is_admin', false)->limit(3)->get() as $student) {
        ExamSubmission::factory()->create([
            'user_id' => $student->id,
            'exam_id' => $exam->id,
            'score' => 80,
        ]);
        ExamSubmission::factory()->create([
            'user_id' => $student->id,
            'exam_id' => $exam->id,
            'score' => null,
        ]);
    }

    return $admin;
}

it('renders TopStudentsWidget with data', function () {
    $this->actingAs(seedDashboardData());

    Livewire::test(TopStudentsWidget::class)
        ->assertSuccessful();
});

it('renders SectionComparisonWidget with data', function () {
    $this->actingAs(seedDashboardData());

    Livewire::test(SectionComparisonWidget::class)
        ->assertSuccessful();
});

it('renders StudentRiskWidget with data', function () {
    $this->actingAs(seedDashboardData());

    Livewire::test(StudentRiskWidget::class)
        ->assertSuccessful();
});

it('renders NeuronUsageWidget with data', function () {
    $this->actingAs(seedDashboardData());

    Livewire::test(NeuronUsageWidget::class)
        ->assertSuccessful();
});

it('renders RecentSupportTicketsWidget with data', function () {
    $this->actingAs(seedDashboardData());

    Livewire::test(RecentSupportTicketsWidget::class)
        ->assertSuccessful();
});

it('renders QuickActionsWidget with data', function () {
    $this->actingAs(seedDashboardData());

    Livewire::test(QuickActionsWidget::class)
        ->assertSuccessful();
});

it('renders ActivityFeedWidget with data', function () {
    $this->actingAs(seedDashboardData());

    Livewire::test(ActivityFeedWidget::class)
        ->assertSuccessful();
});

it('renders ExamPerformanceWidget with data', function () {
    $this->actingAs(seedDashboardData());

    Livewire::test(ExamPerformanceWidget::class)
        ->assertSuccessful();
});

it('renders AdminActivityTrendChart and respects the time range filter', function () {
    $this->actingAs(seedDashboardData());

    Livewire::test(AdminActivityTrendChart::class)
        ->assertSuccessful()
        ->assertSet('filter', '7d')
        ->set('filter', '30d')
        ->assertSuccessful()
        ->assertSet('filter', '30d');
});
