<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain & Path
    |--------------------------------------------------------------------------
    |
    | The dashboard lives at {APP_URL}/horizon. Access is restricted by the
    | `viewHorizon` gate (see AppServiceProvider) to super admins only.
    |
    | NOTE: the laravel/horizon package is installed ONLY inside the Docker
    | image (never in composer.json), so this file must stay free of Horizon
    | class references — plain values only.
    |
    */

    'domain' => env('HORIZON_DOMAIN'),

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    |
    | The Redis connection (from config/database.php) Horizon uses to manage
    | the queues, plus the key prefix so Horizon's keys never collide with
    | the app's own Redis usage.
    |
    */

    'use' => 'default',

    'prefix' => env('HORIZON_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'_horizon:'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    |
    | `web` gives session authentication; the `viewHorizon` gate then limits
    | the dashboard to super admins.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    |
    | Seconds a job may wait on a queue before Horizon flags it as "long wait"
    | on the dashboard. Keyed by "connection:queue".
    |
    */

    'waits' => [
        'redis:ai' => 120,
        'redis:default' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    |
    | How long (in minutes) Horizon keeps job records/metrics before pruning.
    |
    */

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    'silenced' => [
        // App\Jobs\ExampleJob::class,
    ],

    'metrics' => [
        'trim' => [
            'recent' => 24 * 60,
            'completed' => 24 * 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    |
    | Workers finish the current job before terminating when false. Horizon's
    | container role gets a 30s stop grace period from compose either way.
    |
    */

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Worker Memory Limit
    |--------------------------------------------------------------------------
    |
    | Per-worker memory ceiling (MB); a worker exceeding it is recycled. The
    | AI jobs can hold large source texts, so the default matches the old
    | Supervisor worker setting (128M).
    |
    */

    'memory_limit' => (int) env('HORIZON_MEMORY', 128),

    /*
    |--------------------------------------------------------------------------
    | Supervisor Defaults & Environments
    |--------------------------------------------------------------------------
    |
    | One supervisor watches the "ai" queue (question generation, essay
    | grading) plus "default" for everything else. Tunables map to the
    | historical QUEUE_* env vars via the compose files, so existing
    | deployments keep their worker counts without changes.
    |
    */

    'defaults' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('HORIZON_QUEUES', 'ai,default')),
            ))),
            'balance' => 'off',
            'processes' => (int) env('HORIZON_PROCESSES', 4),
            'tries' => (int) env('HORIZON_TRIES', 3),
            'timeout' => (int) env('HORIZON_TIMEOUT', 300),
            'sleep' => 2,
            'memory' => (int) env('HORIZON_MEMORY', 128),
            'nice' => 0,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-1' => [
                'processes' => (int) env('HORIZON_PROCESSES', 4),
            ],
        ],

        'local' => [
            'supervisor-1' => [
                'processes' => (int) env('HORIZON_PROCESSES', 2),
            ],
        ],

        'testing' => [
            'supervisor-1' => [
                'processes' => 1,
            ],
        ],
    ],
];
