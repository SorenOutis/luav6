<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLocalDump extends Command
{
    protected $signature = 'app:import-local-dump
                            {--dump=database/local-dump.json : Path to the dump file (JSON array of SQL statements)}
                            {--yes : Skip the confirmation prompt}';

    protected $description = 'TEMPORARY: Replace the database with a full local dump (DROPs all tables).';

    public function handle(): int
    {
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

        // Best-effort: disable foreign keys for the import. The dump is
        // dependency-ordered so it is FK-safe either way.
        try {
            DB::statement('PRAGMA foreign_keys = OFF');
        } catch (\Throwable $e) {
            $this->line('(PRAGMA foreign_keys not applied: '.$e->getMessage().')');
        }

        $total = count($statements);
        $failures = 0;
        $applied = 0;

        foreach ($statements as $i => $sql) {
            try {
                DB::unprepared($sql);
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
            $this->error("Import FAILED at statement #{$applied} (0-indexed {$applied}). Earlier statements are applied. Re-run is safe (DROP+CREATE is idempotent).");

            return self::FAILURE;
        }

        $this->info("Import complete. {$applied} statements applied.");

        return self::SUCCESS;
    }
}
