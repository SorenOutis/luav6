<?php

declare(strict_types=1);

namespace App\Ai\Watchers;

use App\Models\AiBot;

/**
 * Maps roster slugs to their watcher checks.
 *
 * The eight seeded specialists each own exactly one domain check. Unknown or
 * custom bots get no check and are skipped silently rather than errored, so
 * bot customization never breaks the watcher schedule.
 */
final class BotWatcherRegistry
{
    /** @var array<string, class-string<BotWatcherCheck>> */
    private const MAP = [
        'registrar' => Checks\RegistrarWatcherCheck::class,
        'curriculum-designer' => Checks\CurriculumWatcherCheck::class,
        'assessment-officer' => Checks\AssessmentWatcherCheck::class,
        'student-success-coach' => Checks\StudentSuccessWatcherCheck::class,
        'communications-lead' => Checks\CommunicationsWatcherCheck::class,
        'operations-controller' => Checks\OperationsWatcherCheck::class,
        'data-steward' => Checks\DataStewardWatcherCheck::class,
        'engagement-manager' => Checks\EngagementWatcherCheck::class,
    ];

    public static function checkFor(AiBot $bot): ?BotWatcherCheck
    {
        $class = self::MAP[$bot->slug] ?? null;

        if (! $class) {
            return null;
        }

        return app($class);
    }

    /** @return list<string> */
    public static function domains(): array
    {
        return array_map(fn (string $class): string => app($class)->domain(), array_values(self::MAP));
    }
}
