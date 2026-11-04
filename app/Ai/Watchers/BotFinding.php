<?php

declare(strict_types=1);

namespace App\Ai\Watchers;

/**
 * One candidate anomaly produced by a single watcher check.
 *
 * Evidence holds privacy-safe observations only: counts, ages, and stable
 * identifiers. Never a user's message content, a transcript, or student names.
 */
final class BotFinding
{
    /**
     * @param  list<string>  $evidence
     */
    public function __construct(
        public readonly string $fingerprint,
        public readonly string $severity,
        public readonly string $title,
        public readonly string $summary,
        public readonly array $evidence,
        public readonly ?string $suggestedAction = null,
    ) {
        if ($severity === '' || $title === '' || $summary === '' || $fingerprint === '') {
            throw new \InvalidArgumentException('A bot finding requires fingerprint, severity, title, and summary.');
        }
    }
}
