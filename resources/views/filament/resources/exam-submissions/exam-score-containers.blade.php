@php
    $examSummaries = $this->getVisibleExamScoreExportSummaries();
@endphp

@if ($examSummaries->isNotEmpty())
    <style>
        .exam-score-containers {
            display: grid;
            gap: 0.75rem;
            grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr));
        }

        .exam-score-card {
            border: 1px solid var(--gray-200);
            border-radius: 0.5rem;
            background: var(--gray-50);
            padding: 1rem;
        }

        .dark .exam-score-card {
            border-color: var(--gray-800);
            background: color-mix(in oklab, var(--gray-900) 72%, transparent);
        }

        .exam-score-card__header {
            align-items: flex-start;
            display: flex;
            gap: 0.75rem;
            justify-content: space-between;
        }

        .exam-score-card__title {
            color: var(--gray-950);
            font-size: 0.875rem;
            font-weight: 600;
            line-height: 1.25rem;
        }

        .dark .exam-score-card__title {
            color: var(--gray-100);
        }

        .exam-score-card__meta {
            color: var(--gray-500);
            font-size: 0.75rem;
            line-height: 1rem;
            margin-top: 0.25rem;
        }

        .dark .exam-score-card__meta {
            color: var(--gray-400);
        }

        .exam-score-card__stats {
            display: grid;
            gap: 0.5rem;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            margin-top: 1rem;
        }

        .exam-score-card__stat {
            min-width: 0;
        }

        .exam-score-card__stat-label {
            color: var(--gray-500);
            font-size: 0.6875rem;
            line-height: 1rem;
        }

        .exam-score-card__stat-value {
            color: var(--gray-950);
            font-size: 0.875rem;
            font-weight: 600;
            line-height: 1.25rem;
        }

        .dark .exam-score-card__stat-value {
            color: var(--gray-100);
        }

        .exam-score-card__button {
            align-items: center;
            background: var(--primary-500);
            border-radius: 0.5rem;
            color: white;
            display: inline-flex;
            flex-shrink: 0;
            gap: 0.375rem;
            justify-content: center;
            min-height: 2rem;
            padding: 0.375rem 0.625rem;
            white-space: nowrap;
        }

        .exam-score-card__button:hover {
            background: var(--primary-600);
        }

        .exam-score-card__button-label {
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1rem;
        }
    </style>

    <section class="exam-score-containers">
        @foreach ($examSummaries as $summary)
            <article class="exam-score-card">
                <div class="exam-score-card__header">
                    <div>
                        <h2 class="exam-score-card__title">
                            {{ $summary->exam?->title ?? 'Unknown exam' }}
                        </h2>

                        <p class="exam-score-card__meta">
                            {{ $summary->exam?->section?->name ?? 'No section' }}
                        </p>
                    </div>

                    <button
                        class="exam-score-card__button"
                        type="button"
                        wire:click="exportExamTotalScores({{ (int) $summary->exam_id }})"
                        wire:loading.attr="disabled"
                        wire:target="exportExamTotalScores({{ (int) $summary->exam_id }})"
                    >
                        <x-filament::icon
                            class="fi-icon"
                            icon="heroicon-o-arrow-down-tray"
                        />
                        <span class="exam-score-card__button-label">Export</span>
                    </button>
                </div>

                <dl class="exam-score-card__stats">
                    <div class="exam-score-card__stat">
                        <dt class="exam-score-card__stat-label">Students</dt>
                        <dd class="exam-score-card__stat-value">{{ number_format((int) $summary->student_count) }}</dd>
                    </div>

                    <div class="exam-score-card__stat">
                        <dt class="exam-score-card__stat-label">Parts</dt>
                        <dd class="exam-score-card__stat-value">{{ number_format((int) $summary->submission_count) }}</dd>
                    </div>

                    <div class="exam-score-card__stat">
                        <dt class="exam-score-card__stat-label">Total</dt>
                        <dd class="exam-score-card__stat-value">{{ number_format((float) $summary->total_score, 2) }}</dd>
                    </div>
                </dl>
            </article>
        @endforeach
    </section>
@endif
