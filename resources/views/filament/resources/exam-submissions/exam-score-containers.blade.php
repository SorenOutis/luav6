@php
    $examSummaries = $this->getVisibleExamScoreExportSummaries();
@endphp

@if ($examSummaries->isNotEmpty())
    <style>
        .exam-score-grid {
            display: grid;
            gap: 0.75rem;
            grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
        }

        .exam-score-card {
            border: 1px solid var(--gray-200);
            border-radius: 0.75rem;
            background: var(--gray-50);
            padding: 1rem 1.15rem;
            transition: border-color 150ms ease, box-shadow 150ms ease;
        }

        .dark .exam-score-card {
            border-color: var(--gray-800);
            background: color-mix(in oklab, var(--gray-900) 72%, transparent);
        }

        .exam-score-card:hover {
            border-color: var(--primary-300);
            box-shadow: 0 2px 8px -4px color-mix(in oklab, var(--primary-400) 20%, transparent);
        }

        .dark .exam-score-card:hover {
            border-color: var(--primary-700);
        }

        .exam-score-card__title {
            color: var(--gray-950);
            font-size: 0.8125rem;
            font-weight: 700;
            line-height: 1.25rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .dark .exam-score-card__title {
            color: var(--gray-100);
        }

        .exam-score-card__section {
            color: var(--gray-500);
            font-size: 0.6875rem;
            font-weight: 500;
            line-height: 1rem;
            margin-top: 0.125rem;
        }

        .dark .exam-score-card__section {
            color: var(--gray-400);
        }

        .exam-score-card__stats {
            display: grid;
            gap: 0.75rem;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            margin-top: 0.85rem;
            padding-top: 0.75rem;
            border-top: 1px solid var(--gray-200);
        }

        .dark .exam-score-card__stats {
            border-top-color: var(--gray-800);
        }

        .exam-score-card__stat {
            min-width: 0;
            text-align: center;
        }

        .exam-score-card__stat-value {
            color: var(--gray-950);
            font-size: 1.125rem;
            font-weight: 800;
            line-height: 1.35rem;
            letter-spacing: -0.02em;
        }

        .dark .exam-score-card__stat-value {
            color: white;
        }

        .exam-score-card__stat-label {
            color: var(--gray-500);
            font-size: 0.625rem;
            font-weight: 600;
            line-height: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 0.125rem;
        }

        .dark .exam-score-card__stat-label {
            color: var(--gray-400);
        }
    </style>

    <section class="exam-score-grid">
        @foreach ($examSummaries as $summary)
            <article class="exam-score-card">
                <div>
                    <h2 class="exam-score-card__title" title="{{ $summary->exam?->title ?? 'Unknown exam' }}">
                        {{ $summary->exam?->title ?? 'Unknown exam' }}
                    </h2>
                    <p class="exam-score-card__section">
                        {{ $summary->exam?->section?->name ?? 'No section' }}
                    </p>
                </div>

                <div class="exam-score-card__stats">
                    <div class="exam-score-card__stat">
                        <div class="exam-score-card__stat-value">{{ number_format((int) $summary->student_count) }}</div>
                        <div class="exam-score-card__stat-label">Students</div>
                    </div>
                    <div class="exam-score-card__stat">
                        <div class="exam-score-card__stat-value">{{ number_format((int) $summary->submission_count) }}</div>
                        <div class="exam-score-card__stat-label">Parts</div>
                    </div>
                    <div class="exam-score-card__stat">
                        <div class="exam-score-card__stat-value">{{ number_format((float) $summary->total_score, 2) }}</div>
                        <div class="exam-score-card__stat-label">Total Score</div>
                    </div>
                </div>
            </article>
        @endforeach
    </section>
@endif
