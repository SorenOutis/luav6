<?php

namespace Tests\Reporting;

use PHPUnit\Event\Code\Test;
use PHPUnit\Event\Code\Throwable;
use PHPUnit\Event\Test\ConsideredRisky;
use PHPUnit\Event\Test\ConsideredRiskySubscriber;
use PHPUnit\Event\Test\Errored;
use PHPUnit\Event\Test\ErroredSubscriber;
use PHPUnit\Event\Test\Failed;
use PHPUnit\Event\Test\FailedSubscriber;
use PHPUnit\Event\Test\PhpWarningTriggered;
use PHPUnit\Event\Test\PhpWarningTriggeredSubscriber;
use PHPUnit\Event\Test\WarningTriggered;
use PHPUnit\Event\Test\WarningTriggeredSubscriber;
use PHPUnit\Runner\Extension\Extension as ExtensionInterface;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * Emits GitHub Actions workflow-command annotations for failing/erroring
 * tests, so CI failures are visible as check-run annotations (and in the PR
 * checks view) without downloading raw logs. No-ops outside Actions runners.
 */
final class GithubAnnotationReporter implements ExtensionInterface
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        if (! getenv('GITHUB_ACTIONS')) {
            return;
        }

        try {
            $facade->registerSubscribers(
                new class implements FailedSubscriber {
                    public function notify(Failed $event): void
                    {
                        GithubAnnotationReporter::annotate($event->test(), $event->throwable(), 'failure');
                    }
                },
                new class implements ErroredSubscriber {
                    public function notify(Errored $event): void
                    {
                        GithubAnnotationReporter::annotate($event->test(), $event->throwable(), 'error');
                    }
                },
                new class implements WarningTriggeredSubscriber {
                    public function notify(WarningTriggered $event): void
                    {
                        GithubAnnotationReporter::annotate($event->test(), null, 'warning');
                    }
                },
                new class implements PhpWarningTriggeredSubscriber {
                    public function notify(PhpWarningTriggered $event): void
                    {
                        GithubAnnotationReporter::annotate($event->test(), null, 'php-warning');
                    }
                },
                new class implements ConsideredRiskySubscriber {
                    public function notify(ConsideredRisky $event): void
                    {
                        GithubAnnotationReporter::annotate($event->test(), null, 'risky');
                    }
                },
            );
        } catch (\Throwable) {
            // Ignore subscriber registration failures gracefully
        }
    }

    public static function annotate(mixed $test, ?Throwable $throwable, string $kind): void
    {
        try {
            $testId = is_object($test) && method_exists($test, 'id') ? $test->id() : (string) $test;
            $message = $throwable !== null && method_exists($throwable, 'message')
                ? $throwable->message()
                : $testId;

            // Workflow commands: newlines and % must be escaped.
            $message = str_replace(['%', "\r", "\n"], ['%25', '', '%0A'], trim((string) $message));
            $title = str_replace(['%', "\r", "\n"], ['%25', '', '%0A'], trim($testId.' ['.$kind.']'));

            $file = '';
            if ($throwable !== null && method_exists($throwable, 'stackTrace')) {
                $where = $throwable->stackTrace();
                if (preg_match('/^(.+?):(\d+)/m', $where, $m)) {
                    $file = ' file='.rawurlencode($m[1]).',line='.$m[2];
                }
            }

            // phpcs:ignore
            echo "::error title={$title}{$file}::{$message}\n";
        } catch (\Throwable) {
            // Guard against API variations across PHPUnit event objects
        }
    }
}
