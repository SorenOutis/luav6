<?php

namespace App\Jobs;

use App\Models\AiQuestionDraft;
use App\Services\AiQuestionGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Apply a teacher follow-up instruction to an existing AI question draft —
 * either appending new questions ("add") or rewriting the set ("replace") —
 * using the provider stored on the draft (or the platform default).
 */
class RefineAiQuestions implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public int $draftId,
        public string $instruction,
        public string $mode = 'add',
    ) {
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
                throw new \RuntimeException('Source text is empty. Add source material before sending a follow-up.');
            }

            $existing = (array) ($draft->questions ?? []);

            $new = $service->forProvider($draft->provider)->refine(
                sourceText: $text,
                existingQuestions: $existing,
                instruction: $this->instruction,
                mode: $this->mode === 'replace' ? 'replace' : 'add',
                difficulty: (string) ($draft->difficulty ?? 'medium'),
                topic: $draft->topic,
            );

            if (empty($new)) {
                throw new \RuntimeException('The AI returned no usable questions for that follow-up. Try rephrasing the instruction or switching providers.');
            }

            $draft->forceFill([
                'questions' => $this->mode === 'replace' ? $new : array_merge($existing, $new),
                'status' => 'ready',
                'ai_response' => $service->lastRawResponse,
                'generated_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            // A failed follow-up must never destroy the existing questions —
            // restore "ready" and surface the error on the draft instead.
            $draft->forceFill([
                'status' => 'ready',
                'last_error' => $e->getMessage(),
                'ai_response' => $service->lastRawResponse,
            ])->save();

            throw $e;
        }
    }
}
