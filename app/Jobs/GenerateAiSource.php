<?php

namespace App\Jobs;

use App\Models\AiQuestionDraft;
use App\Services\AiQuestionGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateAiSource implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600; // 10 minutes

    public int $tries = 1;

    public function __construct(
        public int $draftId,
        public string $subject,
        public string $gradeLevel,
        public string $description,
        public int $length
    ) {
        $this->onQueue('ai');
    }

    public function handle(AiQuestionGeneratorService $service): void
    {
        $draft = AiQuestionDraft::query()->withoutGlobalScope('workspace')->find($this->draftId);
        if (! $draft) {
            return;
        }

        $draft->forceFill([
            'status' => 'generating_source',
            'last_error' => null,
        ])->save();

        try {
            $sourceText = $service->forProvider($draft->provider)->generateSource(
                subject: $this->subject,
                gradeLevel: $this->gradeLevel,
                description: $this->description,
                length: $this->length
            );

            if ($sourceText === '') {
                throw new \RuntimeException('AI failed to generate source material.');
            }

            $draft->forceFill([
                'source_text' => $sourceText,
                'status' => 'pending',
            ])->save();

            GenerateAiQuestions::dispatch($draft->id);
        } catch (\Throwable $e) {
            $draft->forceFill([
                'status' => 'failed',
                'last_error' => $e->getMessage(),
            ])->save();

            throw $e;
        }
    }
}
