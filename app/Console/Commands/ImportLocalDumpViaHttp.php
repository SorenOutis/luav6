<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportLocalDumpViaHttp extends Command
{
    protected $signature = 'app:import-local-dump-via-http
                            {--dump=database/local-dump.json : Path to the dump file (JSON array of SQL statements)}
                            {--url= : Libsql HTTP URL (e.g., http://admin:password@host:port)}
                            {--yes : Skip the confirmation prompt}';

    protected $description = 'Import local dump via HTTP API to Dokploy libsql database.';

    public function handle(): int
    {
        $url = $this->option('url') ?: env('TURSO_DATABASE_URL');

        if (empty($url)) {
            $this->error('TURSO_DATABASE_URL is not set or --url not provided.');

            return self::FAILURE;
        }

        // Parse URL to extract host, port, and credentials
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? null;
        $port = $parsed['port'] ?? 8080;
        $user = $parsed['user'] ?? 'admin';
        $pass = $parsed['pass'] ?? '';

        if (empty($host)) {
            $this->error('Invalid URL: cannot extract host.');

            return self::FAILURE;
        }

        $baseUrl = "http://{$host}:{$port}";
        $this->info("Connecting to: {$baseUrl}");

        $path = base_path($this->option('dump'));

        if (! file_exists($path)) {
            $this->error('Dump file not found: '.$path);

            return self::FAILURE;
        }

        $statements = json_decode(file_get_contents($path), true);

        if (! is_array($statements) || count($statements) === 0) {
            $this->error('Dump file is empty or invalid.');

            return self::FAILURE;
        }

        $this->info('Found '.count($statements).' statements in '.$path.'.');
        $this->warn('This will DROP and rebuild every table in the current database.');

        if (! $this->option('yes') && ! $this->confirm('Are you sure you want to replace the entire database?')) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $total = count($statements);
        $failures = 0;
        $applied = 0;

        foreach ($statements as $i => $sql) {
            try {
                $response = Http::withBasicAuth($user, $pass)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post("{$baseUrl}", [
                        'statements' => [
                            [
                                'q' => $sql,
                            ],
                        ],
                    ]);

                if ($response->failed()) {
                    throw new \Exception("HTTP {$response->status()}: {$response->body()}");
                }

                $applied++;
            } catch (\Throwable $e) {
                $failures++;
                $this->error("Statement #{$i} failed: ".$e->getMessage());
                $this->line('SQL: '.mb_substr($sql, 0, 400));
                break;
            }

            if (($i + 1) % 500 === 0 || $i === $total - 1) {
                $this->line("  applied {$applied}/{$total}");
            }
        }

        if ($failures > 0) {
            $this->error("Import FAILED at statement #{$applied}. Earlier statements are applied.");

            return self::FAILURE;
        }

        $this->info("Import complete. {$applied} statements applied.");

        return self::SUCCESS;
    }
}
