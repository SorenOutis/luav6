<?php

namespace App\Ai\Tools;

use App\Services\TopicResearchService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ResearchTopicTool implements Tool
{
    public function __construct(private readonly ?TopicResearchService $researchService = null) {}

    public function name(): string
    {
        return 'research_topic';
    }

    public function description(): Stringable|string
    {
        return 'Search the internet and educational knowledge bases (Wikipedia, encyclopedias) for factual reference materials, core concepts, definitions, and curriculum text on any academic topic. Call this when creating exams, quizzes, or assignments so the teacher never has to supply or paste source material.';
    }

    public function handle(Request $request): Stringable|string
    {
        $admin = auth()->user();
        if (! $admin?->is_admin) {
            return 'Only admins can use this tool.';
        }

        $topic = trim((string) ($request['topic'] ?? ''));
        if ($topic === '') {
            return 'Error: a topic is required to research (e.g. "Photosynthesis", "Python loops", "World War 2 timeline").';
        }

        $service = $this->researchService ?? app(TopicResearchService::class);
        $result = $service->research($topic);

        if ($result && mb_strlen($result) > 100) {
            return "Researched source material for \"{$topic}\":\n\n{$result}\n\nYou can now use this text as source_text in generate_exam_questions or curriculum planning.";
        }

        return "Online reference for \"{$topic}\" returned limited text. As an educational AI assistant, synthesize a comprehensive 500–1,500 word curriculum text from your own knowledge base and pass it as source_text directly.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'topic' => $schema->string()->description('The academic topic, subject, or concept to research (e.g. "Cellular Respiration", "Introduction to JavaScript", "French Revolution").')->required(),
        ];
    }
}
