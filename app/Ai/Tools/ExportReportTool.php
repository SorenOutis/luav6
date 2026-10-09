<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ExportReportTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'export_report';
    }

    public function description(): Stringable|string
    {
        return 'Prepare a real downloadable report as CSV, XLSX, DOCX, or PDF. This tool stages an exact export request for human approval; it never creates or returns a file until the administrator approves the card.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        $type = trim((string) ($request['report_type'] ?? ''));
        $format = strtolower(trim((string) ($request['format'] ?? '')));
        if (! in_array($type, ['exam_answers', 'grades', 'ai_usage'], true)) {
            return 'Error: report_type must be exam_answers, grades, or ai_usage.';
        }
        if (! in_array($format, ['csv', 'xlsx', 'docx', 'pdf'], true)) {
            return 'Error: format must be csv, xlsx, docx, or pdf.';
        }
        if ($type === 'exam_answers' && ! ((int) ($request['exam_id'] ?? 0) > 0)) {
            return 'Error: exam_id is required for exam_answers. Use exams_admin first.';
        }

        $payload = [
            'report_type' => $type,
            'format' => $format,
            'exam_id' => (int) ($request['exam_id'] ?? 0) ?: null,
            'set_id' => (int) ($request['set_id'] ?? 0) ?: null,
            'student_ids' => array_values(array_filter(array_map('intval', (array) ($request['student_ids'] ?? [])))),
            'student_id' => (int) ($request['student_id'] ?? 0) ?: null,
            'section_id' => (int) ($request['section_id'] ?? 0) ?: null,
            'mode' => (string) ($request['mode'] ?? 'students'),
            'include_key' => (bool) ($request['include_key'] ?? true),
        ];

        $title = ucfirst(str_replace('_', ' ', $type)).' export ('.strtoupper($format).')';
        $summary = "Generate {$title} for the active workspace.";
        $changes = [
            ['field' => 'Report', 'before' => null, 'after' => $type],
            ['field' => 'Format', 'before' => null, 'after' => strtoupper($format)],
            ['field' => 'Scope', 'before' => null, 'after' => 'Active workspace only'],
            ['field' => 'Answer key included', 'before' => null, 'after' => $type === 'exam_answers' && $payload['include_key'] ? 'Yes' : 'No'],
            ['field' => 'Download expiry', 'before' => null, 'after' => '24 hours after approval'],
        ];

        return $this->stageAction('export_report', $title, $summary, $payload, $changes);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'report_type' => $schema->string()->description('Report type: exam_answers, grades, or ai_usage.')->required(),
            'format' => $schema->string()->description('Output format: csv, xlsx, docx, or pdf.')->required(),
            'exam_id' => $schema->integer()->description('Required for exam_answers; use exams_admin to resolve it.'),
            'set_id' => $schema->integer()->description('Optional exam-set filter.'),
            'student_ids' => $schema->array()->description('Optional student IDs for an exam answer report.'),
            'student_id' => $schema->integer()->description('Optional student filter for a grades report.'),
            'section_id' => $schema->integer()->description('Optional section filter for a grades report.'),
            'mode' => $schema->string()->description('For exam_answers: key or students. Defaults to students.'),
            'include_key' => $schema->boolean()->description('Whether an exam student report includes the answer key. Defaults to true.'),
        ];
    }
}
