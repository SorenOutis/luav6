<?php

namespace App\Exceptions;

use RuntimeException;

class AiBudgetExceededException extends RuntimeException
{
    public function __construct(
        public readonly string $period,
        public readonly string $metric,
        public readonly int $limit,
        public readonly int $current,
        public readonly int $requested,
    ) {
        $periodLabel = $period === 'monthly' ? 'monthly' : 'daily';
        $metricLabel = $metric === 'cost' ? 'AI cost' : 'AI token';

        parent::__construct(
            "This workspace has reached its {$periodLabel} {$metricLabel} budget. Ask an administrator to increase the limit or wait for the budget to reset.",
        );
    }
}
