<?php

use App\Support\GamificationSyncContext;

it('restores every suppression counter after exceptions', function () {
    $context = new GamificationSyncContext;

    foreach ([
        'section' => fn () => $context->withoutSectionPropagation(
            fn () => throw new RuntimeException('section')
        ),
        'season' => fn () => $context->withoutSeasonPropagation(
            fn () => throw new RuntimeException('season')
        ),
        'history' => fn () => $context->withoutAutomaticHistory(
            fn () => throw new RuntimeException('history')
        ),
    ] as $operation) {
        try {
            $operation();
        } catch (RuntimeException) {
            // Expected: the finally block is what this regression test protects.
        }
    }

    expect($context->sectionPropagationSuppressed())->toBeFalse()
        ->and($context->seasonPropagationSuppressed())->toBeFalse()
        ->and($context->automaticHistorySuppressed())->toBeFalse();
});

it('supports nested suppression without releasing the outer scope early', function () {
    $context = new GamificationSyncContext;

    $context->withoutAutomaticHistory(function () use ($context): void {
        expect($context->automaticHistorySuppressed())->toBeTrue();

        $context->withoutAutomaticHistory(function () use ($context): void {
            expect($context->automaticHistorySuppressed())->toBeTrue();
        });

        expect($context->automaticHistorySuppressed())->toBeTrue();
    });

    expect($context->automaticHistorySuppressed())->toBeFalse();
});
