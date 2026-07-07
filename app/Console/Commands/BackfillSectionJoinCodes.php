<?php

namespace App\Console\Commands;

use App\Models\Section;
use Illuminate\Console\Command;

class BackfillSectionJoinCodes extends Command
{
    protected $signature = 'sections:backfill-join-codes';

    protected $description = 'Generate unique join codes for existing sections that do not have one';

    public function handle(): int
    {
        $sections = Section::whereNull('join_code')->get();

        if ($sections->isEmpty()) {
            $this->info('All sections already have a join code. Nothing to do.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($sections->count());
        $bar->start();

        $updated = 0;
        foreach ($sections as $section) {
            $section->update([
                'join_code' => Section::generateUniqueJoinCode(),
            ]);
            $updated++;
            $bar->advance();
        }

        $bar->finish();

        // Re-fetch so we have the updated join_code values
        $sections = $sections->fresh();

        $this->newLine(2);
        $this->info("Done! Generated join codes for {$updated} section(s):");
        $this->newLine();

        $this->table(
            ['Section', 'Join Code'],
            $sections->map(fn ($s) => [
                $s->name,
                Section::formatJoinCode($s->join_code),
            ])->toArray(),
        );

        return self::SUCCESS;
    }
}
