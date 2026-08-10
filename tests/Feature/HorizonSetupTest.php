<?php

/**
 * Laravel Horizon (Docker-only) setup tests.
 *
 * The package is installed into the Docker image at build time and never
 * appears in composer.json, so these tests only touch package-agnostic
 * surfaces: the committed config/horizon.php values, the `viewHorizon`
 * gate, and the AiQueueWorker spawn guard that defers to Horizon when the
 * queue runs on Redis.
 */

use App\Models\User;
use App\Support\AiQueueWorker;
use Illuminate\Support\Facades\Gate;

it('supervises the ai and default queues on redis', function () {
    $supervisor = config('horizon.defaults.supervisor-1');

    expect($supervisor['connection'])->toBe('redis')
        ->and($supervisor['queue'])->toContain('ai')
        ->and($supervisor['queue'])->toContain('default')
        ->and($supervisor['tries'])->toBe(3)
        ->and($supervisor['timeout'])->toBe(300)
        ->and(config('horizon.use'))->toBe('default')
        ->and(config('horizon.path'))->toBe('horizon');
});

it('scales the production supervisor via HORIZON_PROCESSES', function () {
    expect(config('horizon.environments.production.supervisor-1.processes'))->toBe(4)
        ->and(config('horizon.environments.local.supervisor-1.processes'))->toBe(2);
});

it('allows only super admins to view the horizon dashboard', function () {
    expect(Gate::forUser(User::factory()->superAdmin()->create())->check('viewHorizon'))->toBeTrue()
        ->and(Gate::forUser(User::factory()->admin()->create())->check('viewHorizon'))->toBeFalse()
        ->and(Gate::forUser(User::factory()->create())->check('viewHorizon'))->toBeFalse()
        ->and(Gate::check('viewHorizon'))->toBeFalse(); // guest
});

it('never auto-spawns ad-hoc workers when the queue runs on redis', function () {
    config(['queue.default' => 'redis']);

    // Horizon owns the workers on the Redis driver.
    expect(AiQueueWorker::shouldSpawnWorkers())->toBeFalse();

    config(['queue.default' => 'database']);

    // Still false here, but only because tests never spawn OS processes —
    // outside the suite the database driver keeps the local-dev spawner.
    expect(AiQueueWorker::shouldSpawnWorkers())->toBeFalse();
});
