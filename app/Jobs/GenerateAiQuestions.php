<?php

namespace App\Jobs;

use App\Models\AiQuestionDraft;
use App\Services\AiQuestionGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateAiQuestions implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public int $draftId)
    {
        $this->onQueue('ai');
    }

    public function handle(AiQuestionGeneratorService $service): void
    {
        $draft = AiQuestionDraft::query()->find($this->draftId);
        if (! $draft) {
            return;
        }

        $draft->forceFill([
            'status' => 'running',
            'last_error' => null,
        ])->save();

        try {
            $text = (string) ($draft->source_text ?? '');
            if (trim($text) === '') {
                throw new \RuntimeException('Source text is empty. Upload a readable PDF/DOCX or paste content.');
            }

            $questions = $service->generate(
                sourceText: $text,
                typeCounts: (array) ($draft->type_counts ?? []),
                difficulty: (string) ($draft->difficulty ?? 'medium'),
                topic: $draft->topic,
            );

            if (empty($questions)) {
                throw new \RuntimeException('AI returned no usable questions. Try a shorter/cleaner source or reduce counts.');
            }

            $draft->forceFill([
                'questions' => $questions,
                'status' => 'ready',
                'generated_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            $draft->forceFill([
                'status' => 'failed',
                'last_error' => $e->getMessage(),
            ])->save();

            throw $e;
        }
    }
}
