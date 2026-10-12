<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Daily AI report: {{ $report->report_date->toDateString() }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1f2937; line-height: 1.6; max-width: 600px; margin: 0 auto; padding: 24px; }
        .card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 18px; margin: 16px 0; }
        .summary { font-size: 14.5px; color: #111827; line-height: 1.6; margin: 0; }
        .highlights { margin: 12px 0 0 0; padding-left: 20px; font-size: 13px; color: #374151; }
        .highlights li { margin-bottom: 6px; }
        .meta { font-size: 11px; color: #9ca3af; border-top: 1px solid #f3f4f6; padding-top: 12px; margin-top: 16px; }
    </style>
</head>
<body>
    <div style="margin-bottom: 16px;">
        <p style="margin: 0; font-size: 15px; color: #111827;">
            Good day, <strong>{{ $report->recipient?->name ?? 'Administrator' }}</strong>,
        </p>
        <p style="font-size: 13.5px; color: #4b5563; margin: 6px 0 0 0;">
            Here is your daily executive briefing for <strong>{{ $report->report_date->toDateString() }}</strong>. I have monitored your platform activity across your designated workspaces and prepared the following summary for your review:
        </p>
    </div>

    @if ($report->status !== \App\Models\AiDailyReport::STATUS_COMPLETED)
        <div class="card" style="border-left: 4px solid #f59e0b;">
            <p style="margin: 0;"><strong>Incomplete report.</strong> {{ $report->snapshot === null ? 'Metrics could not be collected. No conclusions are available.' : 'AI analysis unavailable. Verified aggregate metrics follow; no AI conclusions included.' }}</p>
            @if ($report->error_code)
                <p style="font-size: 12px; color: #6b7280; margin: 6px 0 0 0;">Reason: {{ $report->error_code }}</p>
            @endif
        </div>
    @elseif (isset($report->analysis['summary']))
        <div class="card">
            <h3 style="margin-top: 0; margin-bottom: 8px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280;">Executive Briefing</h3>
            <p class="summary">{{ $report->analysis['summary'] }}</p>

            @if (! empty($report->analysis['findings']))
                <h4 style="margin: 14px 0 6px 0; font-size: 12.5px; color: #374151;">Key Takeaways:</h4>
                <ul class="highlights">
                    @foreach ($report->analysis['findings'] as $finding)
                        <li>
                            <strong style="color: #111827;">{{ $finding['title'] ?? '' }}</strong>@if (! empty($finding['detail'])): {{ $finding['detail'] }}@endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    @foreach ($report->snapshot['workspace_names'] ?? [] as $workspaceId => $workspaceName)
        <div style="margin-top: 18px;">
            <h4 style="margin-bottom: 8px; font-size: 13px; color: #374151;">{{ $workspaceName }}</h4>
            <table cellpadding="6" cellspacing="0" border="1" style="width: 100%; border-collapse: collapse; border-color: #e5e7eb; font-size: 12px;">
                <thead>
                    <tr style="background: #f9fafb; text-align: left;"><th>Metric</th><th>Today</th><th>Yesterday</th></tr>
                </thead>
                <tbody>
                    @foreach ($report->snapshot['metrics'] ?? [] as $metric)
                        @if ($metric['workspace_id'] === (int) $workspaceId)
                            <tr>
                                <td>{{ $metric['label'] }}</td>
                                <td>{{ $metric['current'] }} {{ $metric['unit'] }}</td>
                                <td>{{ $metric['previous'] }} {{ $metric['unit'] }}</td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>

            @php
                $workspaceLeaderboards = collect($report->snapshot['section_leaderboards'] ?? [])
                    ->where('workspace_id', (int) $workspaceId);
            @endphp

            @if ($workspaceLeaderboards->isNotEmpty())
                <div style="margin-top: 12px;">
                    <h5 style="margin: 0 0 6px 0; font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280;">Section Leaderboards</h5>
                    <table cellpadding="6" cellspacing="0" border="1" style="width: 100%; border-collapse: collapse; border-color: #e5e7eb; font-size: 12px;">
                        <thead>
                            <tr style="background: #f9fafb; text-align: left;">
                                <th>Section</th>
                                <th>Top Student</th>
                                <th>Level</th>
                                <th>EXP</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($workspaceLeaderboards as $leader)
                                <tr>
                                    <td>{{ $leader['section_name'] }}</td>
                                    <td>{{ $leader['top_student_name'] ?? 'No active students' }}</td>
                                    <td>Level {{ $leader['level'] }}</td>
                                    <td>{{ number_format($leader['exp']) }} EXP</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endforeach

    @if (! empty($report->snapshot['limitations']))
        <div style="margin-top: 16px; font-size: 11px; color: #6b7280;">
            <strong style="color: #4b5563;">Notes:</strong>
            <ul style="margin: 4px 0 0 0; padding-left: 16px;">
                @foreach ($report->snapshot['limitations'] as $limitation)
                    <li>{{ $limitation }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #e5e7eb; font-size: 13px; color: #4b5563;">
        <p style="margin: 0 0 6px 0;">I remain at your service should you require any additional insights.</p>
        <p style="margin: 0; font-weight: 500; color: #111827;">
            Respectfully yours,<br>
            <span style="color: #6b7280; font-weight: 400; font-size: 12px;">Your AI Executive Assistant</span>
        </p>
    </div>

    <div class="meta">
        <span>Report #{{ $report->id }} · {{ $report->timezone }} · Provider: {{ $report->provider }}</span>
        @if ($report->estimated_cost_micros !== null)
            · Est. cost: ${{ number_format($report->estimated_cost_micros / 1000000, 4) }} USD
        @endif
    </div>
</body>
</html>
