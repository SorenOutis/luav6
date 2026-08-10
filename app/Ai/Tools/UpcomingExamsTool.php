<?php

namespace App\Ai\Tools;

use App\Models\Exam;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpcomingExamsTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Get the current student\'s upcoming exams — title, date, duration, status, and section.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $user = auth()->user();

        if (! $user) {
            return 'No user is currently authenticated.';
        }

        $sectionIds = $user->sections()->pluck('sections.id');

        $exams = Exam::query()
            ->with('section:id,name')
            ->where('status', '!=', 'draft')
            ->where(fn ($query) => $query->whereNull('section_id')->orWhereIn('section_id', $sectionIds))
            ->where('exam_date', '>=', now()->subDay())
            ->orderBy('exam_date')
            ->limit(10)
            ->get()
            ->map(fn (Exam $exam) => [
                'title' => $exam->title,
                'exam_date' => $exam->exam_date?->format('M d, Y g:i A'),
                'duration_minutes' => $exam->duration_minutes,
                'status' => $exam->status,
                'section' => $exam->section?->name ?? 'All sections',
            ])
            ->values();

        if ($exams->isEmpty()) {
            return 'The student has no upcoming exams.';
        }

        return json_encode($exams);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
