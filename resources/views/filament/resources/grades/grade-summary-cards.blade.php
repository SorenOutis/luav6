@php
    $gradeSummaries = $this->getVisibleGradeSummaries();
@endphp

@if ($gradeSummaries->isNotEmpty())
    <style>
        .grade-summary-cards {
            display: grid;
            gap: 0.75rem;
            grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr));
        }

        .grade-summary-card {
            border: 1px solid var(--gray-200);
            border-radius: 0.5rem;
            background: var(--gray-50);
            padding: 1rem;
        }

        .dark .grade-summary-card {
            border-color: var(--gray-800);
            background: color-mix(in oklab, var(--gray-900) 72%, transparent);
        }

        .grade-summary-card__title {
            color: var(--gray-950);
            font-size: 0.875rem;
            font-weight: 600;
            line-height: 1.25rem;
        }

        .dark .grade-summary-card__title {
            color: var(--gray-100);
        }

        .grade-summary-card__meta {
            color: var(--gray-500);
            font-size: 0.75rem;
            line-height: 1rem;
            margin-top: 0.25rem;
        }

        .dark .grade-summary-card__meta {
            color: var(--gray-400);
        }

        .grade-summary-card__stats {
            display: grid;
            gap: 0.5rem;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            margin-top: 1rem;
        }

        .grade-summary-card__stat {
            min-width: 0;
        }

        .grade-summary-card__stat-label {
            color: var(--gray-500);
            font-size: 0.6875rem;
            line-height: 1rem;
        }

        .grade-summary-card__stat-value {
            color: var(--gray-950);
            font-size: 0.875rem;
            font-weight: 600;
            line-height: 1.25rem;
        }

        .dark .grade-summary-card__stat-value {
            color: var(--gray-100);
        }
    </style>

    <section class="grade-summary-cards">
        @foreach ($gradeSummaries as $summary)
            @php
                $totalScore = (float) $summary->total_score;
                $totalMaxScore = (float) $summary->total_max_score;
                $average = $totalMaxScore > 0 ? ($totalScore / $totalMaxScore) * 100 : 0;
            @endphp

            <article class="grade-summary-card">
                <h2 class="grade-summary-card__title">
                    {{ $summary->subject ?: 'Untitled subject' }}
                </h2>

                <p class="grade-summary-card__meta">
                    {{ $summary->section?->name ?? 'No section' }}
                </p>

                <dl class="grade-summary-card__stats">
                    <div class="grade-summary-card__stat">
                        <dt class="grade-summary-card__stat-label">Students</dt>
                        <dd class="grade-summary-card__stat-value">{{ number_format((int) $summary->student_count) }}</dd>
                    </div>

                    <div class="grade-summary-card__stat">
                        <dt class="grade-summary-card__stat-label">Grades</dt>
                        <dd class="grade-summary-card__stat-value">{{ number_format((int) $summary->grade_count) }}</dd>
                    </div>

                    <div class="grade-summary-card__stat">
                        <dt class="grade-summary-card__stat-label">Average</dt>
                        <dd class="grade-summary-card__stat-value">{{ number_format($average, 2) }}%</dd>
                    </div>
                </dl>
            </article>
        @endforeach
    </section>
@endif
