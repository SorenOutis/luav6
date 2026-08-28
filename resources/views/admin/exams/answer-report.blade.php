@php
    use Illuminate\Support\Facades\Vite;

    $exam = $report['exam'];
    $students = $report['students'];
    $classSummary = $report['class_summary'];
    $includeKey = $report['include_key'];

    // A report that covers every set mixes parts that usually share the same
    // titles, so the set has to lead the heading. A set-scoped report names
    // its set once, in the header, and leaves the part titles alone.
    $partLabel = static function (array $part) use ($exam): string {
        $prefix = ($exam['set'] ?? null) === null
            && ($exam['set_count'] ?? 1) > 1
            && filled($part['set'] ?? null)
                ? $part['set'].' · '
                : '';

        return 'Part '.$part['number'].' — '.$prefix.$part['title'];
    };

    $studentPartLabel = static fn (array $part): string => $part['title'];

    $resultBadge = static fn (string $result): array => match ($result) {
        'correct' => ['Correct', 'ok'],
        'partial' => ['Partial', 'warn'],
        'wrong' => ['Wrong', 'bad'],
        'scored' => ['Scored', 'ok'],
        'pending' => ['Awaiting grading', 'warn'],
        'graded_manually' => ['Teacher graded', 'warn'],
        default => ['No answer', 'muted'],
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $exam['title'] }} — Answer Report</title>
    <style>
        :root {
            --ink: #18181b;
            --muted: #71717a;
            --line: #d4d4d8;
            --ok: #15803d;
            --ok-bg: #f0fdf4;
            --bad: #b91c1c;
            --bad-bg: #fef2f2;
            --warn: #b45309;
            --warn-bg: #fffbeb;
            --accent: #b45309;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 0 0 4rem;
            background: #f4f4f5;
            color: var(--ink);
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            gap: .5rem;
            align-items: center;
            justify-content: space-between;
            padding: .75rem 1rem;
            background: #ffffff;
            border-bottom: 1px solid var(--line);
        }

        .toolbar__hint { color: var(--muted); font-size: 11.5px; }
        .toolbar__actions { display: flex; gap: .5rem; }

        .btn {
            display: inline-block;
            padding: .45rem .9rem;
            border: 1px solid var(--line);
            border-radius: .4rem;
            background: #fff;
            color: var(--ink);
            font: inherit;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .btn--primary { background: var(--accent); border-color: var(--accent); color: #fff; }

        .sheet {
            max-width: 860px;
            margin: 1.25rem auto;
            padding: 2rem 2.25rem;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: .5rem;
        }

        h1 { font-size: 20px; margin: 0 0 .25rem; }
        h2 { font-size: 15px; margin: 1.75rem 0 .5rem; padding-bottom: .25rem; border-bottom: 2px solid var(--ink); }
        h3 { font-size: 13px; margin: 1.1rem 0 .4rem; }

        .meta { color: var(--muted); font-size: 11.5px; }
        .meta strong { color: var(--ink); }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .5rem 1rem;
            margin: .9rem 0 0;
            padding: .75rem;
            border: 1px solid var(--line);
            border-radius: .4rem;
            background: #fafafa;
        }

        .meta-grid div span { display: block; color: var(--muted); font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; }
        .meta-grid div strong { font-size: 12.5px; }

        table { width: 100%; border-collapse: collapse; margin-top: .5rem; }
        th, td { border: 1px solid var(--line); padding: .4rem .5rem; text-align: left; vertical-align: top; }
        th { background: #fafafa; font-size: 11px; text-transform: uppercase; letter-spacing: .03em; color: var(--muted); }

        .q { border: 1px solid var(--line); border-radius: .4rem; padding: .6rem .75rem; margin-bottom: .5rem; }
        .q--ok { border-left: 4px solid var(--ok); background: var(--ok-bg); }
        .q--bad { border-left: 4px solid var(--bad); background: var(--bad-bg); }
        .q--warn { border-left: 4px solid var(--warn); background: var(--warn-bg); }
        .q--muted { border-left: 4px solid var(--muted); background: #fafafa; }

        .q__head { display: flex; justify-content: space-between; gap: 1rem; align-items: baseline; }
        .q__text { font-weight: 600; }
        .q__type { color: var(--muted); font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; }
        .q__row { margin-top: .3rem; }
        .q__label { display: inline-block; min-width: 110px; color: var(--muted); }
        .q__value--ok { color: var(--ok); font-weight: 600; }
        .q__value--bad { color: var(--bad); font-weight: 600; }
        .essay { white-space: pre-wrap; margin: .2rem 0 0; padding: .4rem .5rem; background: #fff; border: 1px dashed var(--line); border-radius: .3rem; }

        .badge { display: inline-block; padding: .05rem .4rem; border-radius: .25rem; font-size: 10.5px; font-weight: 700; border: 1px solid; }
        .badge--ok { color: var(--ok); border-color: var(--ok); background: var(--ok-bg); }
        .badge--bad { color: var(--bad); border-color: var(--bad); background: var(--bad-bg); }
        .badge--warn { color: var(--warn); border-color: var(--warn); background: var(--warn-bg); }
        .badge--muted { color: var(--muted); border-color: var(--line); background: #fafafa; }

        .options { margin: .3rem 0 0; padding-left: 1.1rem; }
        .options li { margin: .05rem 0; }
        .options li.is-correct { color: var(--ok); font-weight: 600; }

        .scorecard {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: .5rem;
            margin: .6rem 0 .2rem;
        }

        .scorecard div { border: 1px solid var(--line); border-radius: .4rem; padding: .5rem; text-align: center; background: #fafafa; }
        .scorecard span { display: block; color: var(--muted); font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; }
        .scorecard strong { font-size: 16px; }
        .scorecard .ok strong { color: var(--ok); }
        .scorecard .bad strong { color: var(--bad); }

        .student { page-break-before: always; }
        .student:first-of-type { page-break-before: auto; }
        .empty { padding: 1rem; border: 1px dashed var(--line); border-radius: .4rem; color: var(--muted); text-align: center; }
        .footnote { margin-top: 1.5rem; padding-top: .5rem; border-top: 1px solid var(--line); color: var(--muted); font-size: 10.5px; }

        @media print {
            body { background: #fff; font-size: 10.5px; }
            .toolbar { display: none; }
            .sheet { max-width: none; margin: 0; padding: 0; border: 0; border-radius: 0; }
            .q, .student-part { page-break-inside: avoid; }
            @page { size: A4; margin: 14mm 12mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div class="toolbar__hint">
            Use <strong>Print → Destination: “Save as PDF”</strong> to download this report.
        </div>
        <div class="toolbar__actions">
            <a class="btn" href="{{ $backUrl }}">← Back to exam</a>
            <button type="button" class="btn btn--primary" id="print-report">Print / Save as PDF</button>
        </div>
    </div>

    <div class="sheet">
        <h1>{{ $exam['title'] }}</h1>
        <p class="meta">
            Answer report
            @if ($report['mode'] === 'key')
                — answer key only
            @elseif (count($students) === 1)
                — {{ $students[0]['student']['name'] }}
            @else
                — {{ count($students) }} student{{ count($students) === 1 ? '' : 's' }}
            @endif
            · generated {{ $report['generated_at']->timezone(config('app.timezone'))->format('M d, Y H:i') }}
            @if ($report['generated_by'])
                by {{ $report['generated_by'] }}
            @endif
        </p>

        @if ($exam['description'])
            <p class="meta">{{ $exam['description'] }}</p>
        @endif

        <div class="meta-grid">
            <div><span>Section</span><strong>{{ $exam['section'] ?? 'All sections' }}</strong></div>
            @if (($exam['set'] ?? null) !== null || ($exam['set_count'] ?? 0) > 1)
                <div><span>Set</span><strong>{{ $exam['set'] ?? 'All sets' }}</strong></div>
            @endif
            <div><span>Exam date</span><strong>{{ $exam['exam_date']?->format('M d, Y H:i') ?? '—' }}</strong></div>
            <div><span>Duration</span><strong>{{ $exam['duration_minutes'] ? $exam['duration_minutes'].' min' : '—' }}</strong></div>
            <div><span>Status</span><strong>{{ ucfirst($exam['status'] ?? 'draft') }}</strong></div>
            <div><span>Parts</span><strong>{{ $exam['part_count'] }}</strong></div>
            <div><span>Questions</span><strong>{{ $exam['question_count'] }}</strong></div>
            <div><span>Total points</span><strong>{{ $exam['total_points'] }}</strong></div>
            <div><span>Students in report</span><strong>{{ count($students) }}</strong></div>
        </div>

        {{-- ── Class summary ─────────────────────────────────────────── --}}
        @if ($classSummary)
            <h2>Class summary</h2>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Score</th>
                        <th>Percentage</th>
                        <th>Correct</th>
                        <th>Wrong</th>
                        <th>Essays scored</th>
                        <th>No answer</th>
                        <th>Pending</th>
                        <th>Parts</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($students as $index => $student)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $student['student']['name'] }}</td>
                            <td>{{ $student['summary']['score'] }} / {{ $student['summary']['total_points'] }}</td>
                            <td>{{ $student['summary']['percentage'] !== null ? $student['summary']['percentage'].'%' : '—' }}</td>
                            <td>{{ $student['summary']['correct'] }}</td>
                            <td>{{ $student['summary']['wrong'] }}</td>
                            <td>{{ $student['summary']['essays_scored'] }}</td>
                            <td>{{ $student['summary']['unanswered'] }}</td>
                            <td>{{ $student['summary']['pending'] }}</td>
                            <td>{{ $student['summary']['parts_submitted'] }} / {{ $student['summary']['parts_total'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="2">Class average</th>
                        <th>{{ $classSummary['average_score'] }} / {{ $exam['total_points'] }}</th>
                        <th colspan="7">
                            {{ $classSummary['average_percentage'] !== null ? $classSummary['average_percentage'].'%' : '—' }}
                            · highest {{ $classSummary['highest_score'] }} · lowest {{ $classSummary['lowest_score'] }}
                        </th>
                    </tr>
                </tfoot>
            </table>
        @endif

        {{-- ── Answer key ────────────────────────────────────────────── --}}
        @if ($includeKey)
            <h2>Answer key</h2>

            @forelse ($report['parts'] as $part)
                <h3>{{ $partLabel($part) }} ({{ $part['total_points'] }} pts)</h3>
                @if ($part['instructions'])
                    <p class="meta">{{ $part['instructions'] }}</p>
                @endif

                @forelse ($part['questions'] as $question)
                    <div class="q">
                        <div class="q__head">
                            <div class="q__text">{{ $question['number'] }}. {{ $question['text'] }}</div>
                            <div class="q__type">{{ $question['type_label'] }} · {{ $question['points'] }} pt{{ $question['points'] === 1 ? '' : 's' }}</div>
                        </div>

                        @if (count($question['options']))
                            <ul class="options">
                                @foreach ($question['options'] as $option)
                                    <li class="{{ $option['is_correct'] ? 'is-correct' : '' }}">
                                        {{ $option['letter'] }}. {{ $option['text'] }}@if ($option['is_correct']) ✓@endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <div class="q__row">
                            <span class="q__label">{{ $question['type'] === 'essay' ? 'Grading' : 'Correct answer' }}</span>
                            <span class="{{ $question['type'] === 'essay' ? 'meta' : 'q__value--ok' }}">{{ $question['correct_display'] }}</span>
                        </div>
                    </div>
                @empty
                    <div class="empty">This part has no questions yet.</div>
                @endforelse
            @empty
                <div class="empty">This exam has no parts yet.</div>
            @endforelse
        @endif

        {{-- ── Per-student reports ───────────────────────────────────── --}}
        @if ($report['mode'] !== 'key')
            @forelse ($students as $student)
                <div class="student">
                    <h2>
                        {{ $student['student']['name'] }}
                        @if (filled($student['student']['set'] ?? null))
                            <span class="meta">— {{ $student['student']['set'] }}</span>
                        @endif
                        — graded answers
                    </h2>
                    <p class="meta">
                        {{ $student['student']['email'] }}
                        · submitted {{ $student['summary']['submitted_at']?->format('M d, Y H:i') ?? '—' }}
                        @if ($student['summary']['is_late'])
                            · <strong>Late submission</strong>
                        @endif
                        @if ($student['summary']['has_pending_grading'])
                            · <strong>Some answers are still being graded</strong>
                        @endif
                    </p>

                    <div class="scorecard">
                        <div><span>Overall score</span><strong>{{ $student['summary']['score'] }} / {{ $student['summary']['total_points'] }}</strong></div>
                        <div><span>Percentage</span><strong>{{ $student['summary']['percentage'] !== null ? $student['summary']['percentage'].'%' : '—' }}</strong></div>
                        <div class="ok"><span>Correct</span><strong>{{ $student['summary']['correct'] }}</strong></div>
                        <div class="bad"><span>Wrong</span><strong>{{ $student['summary']['wrong'] }}</strong></div>
                        <div><span>Essay points</span><strong>{{ $student['summary']['essay_points'] }}</strong></div>
                        <div><span>Partial credit</span><strong>{{ $student['summary']['partial'] }}</strong></div>
                        <div><span>No answer / pending</span><strong>{{ $student['summary']['unanswered'] }} / {{ $student['summary']['pending'] }}</strong></div>
                    </div>

                    @foreach ($student['parts'] as $partReport)
                        @php $part = $partReport['part']; @endphp
                        <div class="student-part">
                            <h3>
                                {{ $studentPartLabel($part) }}
                                <span class="meta">
                                    ({{ $partReport['score'] !== null ? $partReport['score'] : 0 }} / {{ $partReport['total_points'] }} pts ·
                                    {{ $partReport['status_label'] }}@if ($partReport['is_late']) · late @endif)
                                </span>
                            </h3>

                            @if (! $partReport['submitted'])
                                <div class="empty">No submission for this part.</div>
                            @endif

                            @foreach ($partReport['items'] as $item)
                                @php
                                    $question = $item['question'];
                                    [$badgeText, $badgeTone] = $resultBadge($item['result']);
                                @endphp
                                <div class="q q--{{ $badgeTone }}">
                                    <div class="q__head">
                                        <div class="q__text">{{ $question['number'] }}. {{ $question['text'] }}</div>
                                        <div class="q__type">
                                            <span class="badge badge--{{ $badgeTone }}">{{ $badgeText }}</span>
                                            {{ $item['earned_known'] ? $item['earned'] : '?' }} / {{ $question['points'] }}
                                        </div>
                                    </div>

                                    @if ($question['type'] === 'essay')
                                        <div class="q__row">
                                            <span class="q__label">Answer</span>
                                            @if ($item['student_answer'])
                                                <div class="essay">{{ $item['student_answer'] }}</div>
                                            @else
                                                <span class="meta">— no answer —</span>
                                            @endif
                                        </div>

                                        @if ($item['feedback'])
                                            <div class="q__row">
                                                <span class="q__label">{{ $item['feedback_source'] === 'teacher' ? 'Teacher feedback' : 'AI feedback' }}</span>
                                                <div class="essay">{{ $item['feedback'] }}</div>
                                            </div>
                                        @endif

                                        @if ($item['teacher_feedback'])
                                            <div class="q__row">
                                                <span class="q__label">Teacher feedback</span>
                                                <div class="essay">{{ $item['teacher_feedback'] }}</div>
                                            </div>
                                        @endif

                                        @if (! $item['feedback'] && ! $item['teacher_feedback'])
                                            <div class="q__row">
                                                <span class="q__label">Feedback</span>
                                                <span class="meta">— none recorded —</span>
                                            </div>
                                        @endif

                                        <div class="q__row">
                                            <span class="q__label">Score</span>
                                            <span class="{{ $item['earned_known'] ? 'q__value--ok' : '' }}">
                                                @if ($item['earned_known'])
                                                    {{ $item['earned'] }} / {{ $question['points'] }} points
                                                    <span class="meta">· {{ $question['grading_method'] === 'manual' ? 'graded by the teacher' : 'graded automatically by AI' }}</span>
                                                @else
                                                    Not scored per question — counted in the part total ({{ $question['points'] }} points possible)
                                                @endif
                                            </span>
                                        </div>
                                    @else
                                        <div class="q__row">
                                            <span class="q__label">Student answer</span>
                                            <span class="{{ $item['result'] === 'correct' ? 'q__value--ok' : ($item['result'] === 'wrong' ? 'q__value--bad' : '') }}">
                                                {{ $item['student_answer'] ?? '— no answer —' }}
                                            </span>
                                        </div>
                                        @if ($question['type'] === 'enumeration')
                                            <div class="q__row">
                                                <span class="q__label">Item breakdown</span>
                                                <ul class="options">
                                                    @foreach ($item['enumeration_breakdown'] ?? [] as $enumerationItem)
                                                        <li class="{{ $enumerationItem['matched'] ? 'is-correct' : '' }}">
                                                            {{ $enumerationItem['answer'] }}
                                                            <span class="meta">{{ $enumerationItem['earned'] }} / {{ $enumerationItem['points'] }} pts</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @elseif ($question['type'] === 'matching')
                                            <div class="q__row">
                                                <span class="q__label">Pair breakdown</span>
                                                <ul class="options">
                                                    @foreach ($item['matching_breakdown'] ?? [] as $matchingPair)
                                                        <li class="{{ $matchingPair['matched'] ? 'is-correct' : '' }}">
                                                            <strong>{{ $matchingPair['prompt'] }}</strong>
                                                            <span class="meta">
                                                                Student: {{ $matchingPair['submitted'] !== '' ? $matchingPair['submitted'] : 'No answer' }}
                                                                | Expected: {{ $matchingPair['expected'] }}
                                                                | {{ $matchingPair['earned'] }} / {{ $matchingPair['points'] }} pts
                                                            </span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @else
                                            <div class="q__row">
                                                <span class="q__label">Correct answer</span>
                                                <span class="q__value--ok">{{ $question['correct_display'] }}</span>
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            @endforeach

                            @if ($partReport['feedback'])
                                <div class="q q--muted">
                                    <span class="q__label">Teacher feedback</span>
                                    <div class="essay">{{ $partReport['feedback'] }}</div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @empty
                <h2>Student answers</h2>
                <div class="empty">No submissions found for the selected students.</div>
            @endforelse
        @endif

        <p class="footnote">
            Part scores come from the recorded submission score. Essays are reported as answer, feedback and score —
            never as right or wrong. An essay marked manually shows “?” per question because teacher marks are recorded
            on the part total, not per question.
        </p>
    </div>

    <script nonce="{{ Vite::cspNonce() }}">
        document.getElementById('print-report').addEventListener('click', function () {
            window.print();
        });

        @if ($autoPrint)
            window.addEventListener('load', function () {
                window.setTimeout(function () { window.print(); }, 350);
            });
        @endif
    </script>
</body>
</html>
