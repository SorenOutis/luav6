<?php

use App\Ai\Agents\AdminAssistantAgent;
use App\Ai\Tools\GenerateExamQuestionsTool;
use App\Ai\Tools\ResearchTopicTool;
use App\Models\Exam;
use App\Models\User;
use App\Services\TopicResearchService;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

it('fetches topic summary through TopicResearchService', function () {
    Http::fake([
        'https://en.wikipedia.org/api/rest_v1/page/summary/*' => Http::response([
            'title' => 'Photosynthesis',
            'description' => 'Biological process using light',
            'extract' => 'Photosynthesis is a system of biological processes by which organisms convert light into chemical energy.',
        ], 200),
    ]);

    $service = new TopicResearchService;
    $result = $service->research('Photosynthesis');

    expect($result)->toContain('Topic: Photosynthesis')
        ->and($result)->toContain('Overview: Biological process using light')
        ->and($result)->toContain('convert light into chemical energy');
});

it('handles network failure gracefully in TopicResearchService', function () {
    Http::fake([
        '*' => Http::response('Service unavailable', 500),
    ]);

    $service = new TopicResearchService;
    $result = $service->research('Nonexistent Topic');

    expect($result)->toBeNull();
});

it('restricts ResearchTopicTool to administrators', function () {
    $student = User::factory()->create();
    $this->actingAs($student);

    $tool = new ResearchTopicTool;
    $result = $tool->handle(new Request(['topic' => 'Python']));

    expect($result)->toContain('Only admins');
});

it('requires a topic in ResearchTopicTool', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $tool = new ResearchTopicTool;
    $result = $tool->handle(new Request(['topic' => '   ']));

    expect($result)->toContain('Error: a topic is required');
});

it('returns researched topic source text in ResearchTopicTool', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Http::fake([
        'https://en.wikipedia.org/api/rest_v1/page/summary/*' => Http::response([
            'title' => 'Cellular Respiration',
            'description' => 'Metabolic pathway',
            'extract' => 'Cellular respiration is a series of chemical reactions that break down glucose to produce ATP, which can be used as energy to power many reactions throughout the body.',
        ], 200),
    ]);

    $tool = new ResearchTopicTool;
    $result = $tool->handle(new Request(['topic' => 'Cellular Respiration']));

    expect($result)->toContain('Researched source material for "Cellular Respiration"')
        ->and($result)->toContain('break down glucose to produce ATP')
        ->and($result)->toContain('generate_exam_questions');
});

it('auto-researches topic when source_text is empty in GenerateExamQuestionsTool', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $exam = Exam::factory()->create(['title' => 'Biology Exam']);

    Http::fake([
        'https://en.wikipedia.org/api/rest_v1/page/summary/*' => Http::response([
            'title' => 'Mitosis',
            'description' => 'Cell division',
            'extract' => 'Mitosis is a part of the cell cycle in which replicated chromosomes are separated into two new nuclei. Cell division gives rise to genetically identical cells.',
        ], 200),
    ]);

    $tool = new GenerateExamQuestionsTool;
    $result = $tool->handle(new Request([
        'exam_id' => $exam->id,
        'source_text' => '',
        'topic' => 'Mitosis',
        'multiple_choice' => 5,
    ]));

    expect($result)->toContain('PENDING HUMAN APPROVAL');
});

it('registers ResearchTopicTool in AdminAssistantAgent', function () {
    $agent = new AdminAssistantAgent;
    $tools = collect($agent->tools())->map(fn ($t) => $t->name())->all();

    expect($tools)->toContain('research_topic')
        ->and((string) $agent->instructions())->toContain('research_topic');
});
