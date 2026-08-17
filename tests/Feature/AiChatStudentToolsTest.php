<?php

/**
 * Student chat tool tests.
 *
 * Every student-facing tool is read-only and strictly scoped to the
 * authenticated student — no tool accepts a user identifier, so one student
 * can never read another's data. ClaimDailyXpTool is the single write and
 * is idempotent via ClaimXpService.
 */

use App\Ai\Tools\AnnouncementsTool;
use App\Ai\Tools\AssignmentsTool;
use App\Ai\Tools\ClaimDailyXpTool;
use App\Ai\Tools\ExamResultsTool;
use App\Ai\Tools\GradesTool;
use App\Ai\Tools\LessonsTool;
use App\Ai\Tools\ProgressTool;
use App\Ai\Tools\UpcomingExamsTool;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\ExamSubmission;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\LessonUserProgress;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use Laravel\Ai\Tools\Request;

it('shows only upcoming exams for the student\'s sections, never drafts', function () {
    $season = Season::factory()->active()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);
    $sectionA = Section::factory()->forSeason($season)->create();
    $sectionB = Section::factory()->forSeason($season)->create();

    Exam::factory()->create(['title' => 'Biology Midterm', 'section_id' => $sectionA->id, 'status' => 'published', 'exam_date' => now()->addDays(3)]);
    Exam::factory()->create(['title' => 'Chemistry Final', 'section_id' => $sectionB->id, 'status' => 'published', 'exam_date' => now()->addDays(4)]);
    Exam::factory()->create(['title' => 'Secret Draft Exam', 'section_id' => $sectionA->id, 'status' => 'draft', 'exam_date' => now()->addDays(2)]);

    $student = User::factory()->create();
    $student->sections()->attach($sectionA->id, ['season_id' => $season->id]);

    $this->actingAs($student);

    $result = (new UpcomingExamsTool)->handle(new Request([]));

    expect($result)->toContain('Biology Midterm')
        ->not->toContain('Chemistry Final')
        ->not->toContain('Secret Draft Exam');
});

it('shows only the student\'s own exam results', function () {
    $exam = Exam::factory()->create(['status' => 'published']);
    $part = ExamPart::factory()->create(['exam_id' => $exam->id]);
    $student = User::factory()->create();
    $other = User::factory()->create();

    ExamSubmission::create(['user_id' => $student->id, 'exam_id' => $exam->id, 'exam_part_id' => $part->id, 'answers' => [], 'status' => 'graded', 'score' => 87.5, 'feedback' => 'Strong analysis']);
    ExamSubmission::create(['user_id' => $other->id, 'exam_id' => $exam->id, 'exam_part_id' => $part->id, 'answers' => [], 'status' => 'graded', 'score' => 12.5, 'feedback' => 'Needs work']);

    $this->actingAs($student);

    $result = (new ExamResultsTool)->handle(new Request([]));

    expect($result)->toContain('87.5')->toContain('Strong analysis')
        ->not->toContain('12.5')->not->toContain('Needs work');
});

it('shows only the student\'s own grades', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $section = Section::factory()->create();

    $student = User::factory()->create();
    $other = User::factory()->create();
    $student->sections()->attach($section->id, ['season_id' => $section->season_id]);
    $other->sections()->attach($section->id, ['season_id' => $section->season_id]);

    Grade::create(['user_id' => $student->id, 'section_id' => $section->id, 'subject' => 'Mathematics', 'period' => 'Prelim', 'score' => 90, 'max_score' => 100, 'recorded_by' => $admin->id]);
    Grade::create(['user_id' => $other->id, 'section_id' => $section->id, 'subject' => 'Filipino', 'period' => 'Prelim', 'score' => 45, 'max_score' => 100, 'recorded_by' => $admin->id]);

    $this->actingAs($student);

    $result = (new GradesTool)->handle(new Request([]));

    expect($result)->toContain('Mathematics')->not->toContain('Filipino');
});

it('returns the student\'s gamification progress', function () {
    Season::factory()->active()->create();
    $student = User::factory()->create(['current_streak' => 7]);

    $this->actingAs($student);

    $data = json_decode((new ProgressTool)->handle(new Request([])), true);

    expect($data)->toHaveKeys(['system_level', 'total_xp', 'points', 'streak_days', 'badges', 'sections'])
        ->and($data['streak_days'])->toBe(7);
});

it('shows course progress with the next incomplete lesson', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $course = Course::create(['name' => 'Biology 101', 'admin_id' => $admin->id]);
    $module = CourseModule::create(['course_id' => $course->id, 'title' => 'Foundations', 'sort_order' => 1, 'admin_id' => $admin->id]);
    $lessonOne = Lesson::create(['course_module_id' => $module->id, 'title' => 'Intro to Cells', 'sort_order' => 1, 'admin_id' => $admin->id]);
    $lessonTwo = Lesson::create(['course_module_id' => $module->id, 'title' => 'Cell Division', 'sort_order' => 2, 'admin_id' => $admin->id]);

    $student = User::factory()->create();
    $student->courses()->attach($course->id);
    LessonUserProgress::create(['user_id' => $student->id, 'lesson_id' => $lessonOne->id, 'completed' => true, 'completed_at' => now()]);

    $this->actingAs($student);

    $result = (new LessonsTool)->handle(new Request([]));

    expect($result)->toContain('Biology 101')
        ->toContain('"completed_lessons":1')
        ->toContain('"next_lesson":"Cell Division"');
});

it('shows only active announcements', function () {
    Announcement::create(['title' => 'Enrollment Week', 'description' => 'Enroll now', 'is_active' => true]);
    Announcement::create(['title' => 'Old Memo', 'description' => 'Outdated', 'is_active' => false]);

    $this->actingAs(User::factory()->create());

    $result = (new AnnouncementsTool)->handle(new Request([]));

    expect($result)->toContain('Enrollment Week')->not->toContain('Old Memo');
});

it('lists course assignments with submission state', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $course = Course::create(['name' => 'Biology 101', 'admin_id' => $admin->id]);
    Assignment::create(['title' => 'Cell Model Project', 'course_id' => $course->id, 'due_date' => now()->addWeek()]);

    $student = User::factory()->create();
    $student->courses()->attach($course->id);

    $this->actingAs($student);

    $result = (new AssignmentsTool)->handle(new Request([]));

    expect($result)->toContain('Cell Model Project')->toContain('"submitted":false');
});

it('claims the daily XP reward only once per day', function () {
    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create();
    $student = User::factory()->create(['last_claimed_at' => null]);
    $student->sections()->attach($section->id, ['season_id' => $season->id]);

    $this->actingAs($student);

    $first = (new ClaimDailyXpTool)->handle(new Request([]));
    $second = (new ClaimDailyXpTool)->handle(new Request([]));

    expect($first)->toContain('"claimed":true')
        ->and($second)->toContain('"claimed":false');
});
