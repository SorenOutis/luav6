<?php

use App\Ai\Tools\GenerateExamQuestionsTool;
use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\User;
use App\Services\AiQuestionGeneratorService;
use Laravel\Ai\Tools\Request;

/**
 * Test-only generator that returns predictable questions without any network
 * call, and records the arguments it was given so tests can assert the tool
 * passes the right source/counts/difficulty/topic.
 */
class StubQuestionGenerator extends AiQuestionGeneratorService
{
    public array $lastCall = [];

    public bool $shouldReturnEmpty = false;

    public function generate(string $sourceText, array $typeCounts, string $difficulty = 'medium', ?string $topic = null): array
    {
        $this->lastCall = compact('sourceText', 'typeCounts', 'difficulty', 'topic');

        if ($this->shouldReturnEmpty) {
            return [];
        }

        $questions = [];

        foreach ([
            'multiple_choice' => ['options' => true],
            'true_false' => ['options' => true],
            'identification' => ['correct_answer' => true],
            'essay' => [],
        ] as $type => $shape) {
            for ($i = 0; $i < (int) ($typeCounts[$type] ?? 0); $i++) {
                $q = [
                    'type' => $type,
                    'text' => ucfirst(str_replace('_', ' ', $type))." question {$i}",
                ];

                if (($shape['options'] ?? false) === true) {
                    $q['options'] = [
                        ['text' => 'Option A', 'is_correct' => true],
                        ['text' => 'Option B', 'is_correct' => false],
                    ];
                }

                if (($shape['correct_answer'] ?? false) === true) {
                    $q['correct_answer'] = 'Answer';
                }

                $questions[] = $q;
            }
        }

        return $questions;
    }
}
it('attaches generated questions to the exam as grouped parts after confirmation', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $exam = Exam::factory()->create(['title' => 'Cell Biology Midterm']);

    $fake = new StubQuestionGenerator;
    $this->app->instance(AiQuestionGeneratorService::class, $fake);

    $tool = new GenerateExamQuestionsTool;

    $result = $tool->handle(new Request([
        'exam_id' => $exam->id,
        'source_text' => 'The mitochondrion produces ATP.',
        'topic' => 'Cellular respiration',
        'difficulty' => 'hard',
        'multiple_choice' => 2,
        'true_false' => 1,
        'identification' => 1,
        'essay' => 1,
        'points' => 2,
        'confirm' => true,
    ]));

    expect($result)->toMatch('/Attached 4 question part\(s\) to "Cell Biology Midterm" \(exam ID \d+\)/');

    // The tool passed the right arguments to the generator.
    expect($fake->lastCall['sourceText'])->toBe('The mitochondrion produces ATP.')
        ->and($fake->lastCall['difficulty'])->toBe('hard')
        ->and($fake->lastCall['topic'])->toBe('Cellular respiration')
        ->and($fake->lastCall['typeCounts'])->toBe([
            'multiple_choice' => 2,
            'true_false' => 1,
            'identification' => 1,
            'essay' => 1,
        ]);

    // One part per requested type, in canonical order.
    $parts = ExamPart::where('exam_id', $exam->id)->orderBy('sort_order')->get();

    expect($parts)->toHaveCount(4)
        ->and($parts->pluck('title')->all())->toBe([
            'Part I - Multiple Choice',
            'Part II - True or False',
            'Part III - Identification',
            'Part IV - Essay',
        ])
        ->and($parts->pluck('type')->all())->toBe(['section', 'section', 'section', 'section'])
        ->and($parts->pluck('sort_order')->all())->toBe([1, 2, 3, 4])
        ->and($parts->pluck('points')->all())->toBe([2, 2, 2, 2]);

    // The MC part holds exactly the two generated MC questions.
    expect($parts[0]->questions)->toHaveCount(2)
        ->and($parts[0]->questions[0]['text'])->toBe('Multiple choice question 0')
        ->and($parts[0]->questions[0]['points'])->toBe(2)
        ->and($parts[0]->questions[0]['options'][0]['is_correct'])->toBeTrue();

    // Default per-type instructions are applied when none provided.
    expect($parts[0]->instructions)->toBe('Choose the best answer for each item.')
        ->and($parts[2]->instructions)->toBe('Write the term or phrase being described.');
});

it('refuses non-admins', function () {
    $this->actingAs(User::factory()->create());

    $result = (new GenerateExamQuestionsTool(new StubQuestionGenerator))->handle(new Request([
        'exam_id' => 1,
        'source_text' => 'Some material.',
        'confirm' => true,
    ]));

    expect($result)->toContain('Only admins')
        ->and(ExamPart::count())->toBe(0);
});

it('does not generate without confirmation', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $exam = Exam::factory()->create();

    $result = (new GenerateExamQuestionsTool(new StubQuestionGenerator))->handle(new Request([
        'exam_id' => $exam->id,
        'source_text' => 'Some material.',
        'multiple_choice' => 1,
        'confirm' => false,
    ]));

    expect($result)->toContain('NOT EXECUTED')
        ->and(ExamPart::count())->toBe(0);
});

it('rejects an exam from another workspace', function () {
    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();

    $this->actingAs($adminB);
    $foreignExam = Exam::factory()->create();

    $this->actingAs($adminA);

    $result = (new GenerateExamQuestionsTool(new StubQuestionGenerator))->handle(new Request([
        'exam_id' => $foreignExam->id,
        'source_text' => 'Some material.',
        'multiple_choice' => 1,
        'confirm' => true,
    ]));

    expect($result)->toContain('not found')
        ->and(ExamPart::count())->toBe(0);
});
it('requires source text', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $exam = Exam::factory()->create();

    $result = (new GenerateExamQuestionsTool(new StubQuestionGenerator))->handle(new Request([
        'exam_id' => $exam->id,
        'source_text' => '   ',
        'multiple_choice' => 1,
        'confirm' => true,
    ]));

    expect($result)->toContain('source_text is required')
        ->and(ExamPart::count())->toBe(0);
});

it('requires at least one question', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $exam = Exam::factory()->create();

    $result = (new GenerateExamQuestionsTool(new StubQuestionGenerator))->handle(new Request([
        'exam_id' => $exam->id,
        'source_text' => 'Some material.',
        'confirm' => true,
    ]));

    expect($result)->toContain('at least one question')
        ->and(ExamPart::count())->toBe(0);
});

it('rejects more than 100 total questions', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $exam = Exam::factory()->create();

    $result = (new GenerateExamQuestionsTool(new StubQuestionGenerator))->handle(new Request([
        'exam_id' => $exam->id,
        'source_text' => 'Some material.',
        'multiple_choice' => 30,
        'true_false' => 30,
        'identification' => 30,
        'essay' => 11,
        'confirm' => true,
    ]));

    expect($result)->toContain('too many questions')
        ->and(ExamPart::count())->toBe(0);
});

it('handles the AI returning no usable questions', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $exam = Exam::factory()->create();

    $fake = new StubQuestionGenerator;
    $fake->shouldReturnEmpty = true;
    $this->app->instance(AiQuestionGeneratorService::class, $fake);

    $result = (new GenerateExamQuestionsTool)->handle(new Request([
        'exam_id' => $exam->id,
        'source_text' => 'Some material.',
        'multiple_choice' => 1,
        'confirm' => true,
    ]));

    expect($result)->toContain('no usable questions')
        ->and(ExamPart::count())->toBe(0);
});
