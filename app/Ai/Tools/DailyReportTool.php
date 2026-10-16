<?php

namespace App\Ai\Tools;

use App\Models\AiDailyReport;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DailyReportTool implements Tool
{
    public function name(): string
    {
        return 'daily_report';
    }

    public function description(): Stringable|string
    {
        return 'Fetch the nightly AI platform report for the Super Administrator: summary, findings with severity, recommendations, and the underlying metrics for a given date or the latest available report. Read-only.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = auth()->user();
        if (! $user?->isSuperAdmin()) {
            return 'Error: daily reports are available to Super Administrators only.';
        }

        $query = AiDailyReport::query()->orderByDesc('report_date')->orderByDesc('id');

        $date = trim((string) ($request['date'] ?? ''));
        if ($date !== '') {
            try {
                $date = Carbon::parse($date)->toDateString();
            } catch (\Throwable) {
                return 'Error: invalid date format. Use YYYY-MM-DD.';
            }

            $query->where('report_date', $date);
        }

        $report = $query->first();

        if (! $report) {
            return $date !== ''
                ? "No daily report exists for {$date}."
                : 'No daily reports have been generated yet.';
        }

        $snapshot = is_array($report->snapshot) ? $report->snapshot : [];
        $analysis = is_array($report->analysis) ? $report->analysis : null;

        return json_encode([
            'report_date' => $report->report_date?->toDateString(),
            'status' => $report->status,
            'completed_at' => $report->completed_at?->toDateTimeString(),
            'provider' => $report->provider,
            'model' => $report->model,
            'workspaces' => $snapshot['workspace_names'] ?? [],
            'metrics' => $snapshot['metrics'] ?? [],
            'limitations' => $snapshot['limitations'] ?? [],
            'analysis' => $analysis,
            'error_code' => $report->error_code,
            'note' => $analysis === null
                ? 'This report has no analysis yet (status: '.$report->status.'). If it failed, a Super Administrator can rerun it from the AI Daily Reports page.'
                : null,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'date' => $schema->string()->description('Optional report date in YYYY-MM-DD format. Defaults to the most recent report.'),
        ];
    }
}
