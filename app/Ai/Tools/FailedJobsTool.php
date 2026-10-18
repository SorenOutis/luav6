<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class FailedJobsTool implements Tool
{
    public function name(): string
    {
        return 'failed_jobs';
    }

    public function description(): Stringable|string
    {
        return 'Super Administrator only. Summarize recent failed queue jobs (id, connection, queue, exception snippet, failed_at). Use for platform health / "why are jobs failing" questions.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = auth()->user();
        if (! $user?->isSuperAdmin()) {
            return json_encode([
                'error' => 'platform_tool_required',
                'message' => 'failed_jobs is platform-wide and only available to Super Administrators.',
            ]);
        }

        if (! Schema::hasTable('failed_jobs')) {
            return json_encode([
                'scope' => 'platform-wide',
                'available' => false,
                'message' => 'The failed_jobs table is not present in this environment.',
                'jobs' => [],
            ]);
        }

        $limit = max(1, min(50, (int) ($request['limit'] ?? 20)));

        $rows = DB::table('failed_jobs')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'uuid', 'connection', 'queue', 'exception', 'failed_at']);

        $jobs = $rows->map(function ($row): array {
            $exception = (string) ($row->exception ?? '');
            $firstLine = strtok($exception, "\n") ?: $exception;

            return [
                'id' => $row->id,
                'uuid' => $row->uuid ?? null,
                'connection' => $row->connection,
                'queue' => $row->queue,
                'exception' => mb_substr($firstLine, 0, 240),
                'failed_at' => $row->failed_at,
            ];
        })->values();

        return json_encode([
            'scope' => 'platform-wide',
            'available' => true,
            'count' => $jobs->count(),
            'jobs' => $jobs,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->description('Max failed jobs to return (1-50, default 20).'),
        ];
    }
}
