<?php

namespace App\Support;

use Closure;

/**
 * Request/job-scoped recursion and side-effect control for progress observers.
 *
 * Counters (rather than booleans) make nested operations safe. Every temporary
 * suppression is restored in a finally block, so an exception cannot poison an
 * Octane worker or the next queue job handled by the same PHP process.
 */
class GamificationSyncContext
{
    private int $sectionPropagationDepth = 0;

    private int $seasonPropagationDepth = 0;

    private int $automaticHistoryDepth = 0;

    public function sectionPropagationSuppressed(): bool
    {
        return $this->sectionPropagationDepth > 0;
    }

    public function seasonPropagationSuppressed(): bool
    {
        return $this->seasonPropagationDepth > 0;
    }

    public function automaticHistorySuppressed(): bool
    {
        return $this->automaticHistoryDepth > 0;
    }

    public function withoutSectionPropagation(Closure $callback): mixed
    {
        $this->sectionPropagationDepth++;

        try {
            return $callback();
        } finally {
            $this->sectionPropagationDepth--;
        }
    }

    public function withoutSeasonPropagation(Closure $callback): mixed
    {
        $this->seasonPropagationDepth++;

        try {
            return $callback();
        } finally {
            $this->seasonPropagationDepth--;
        }
    }

    public function withoutAutomaticHistory(Closure $callback): mixed
    {
        $this->automaticHistoryDepth++;

        try {
            return $callback();
        } finally {
            $this->automaticHistoryDepth--;
        }
    }
}
