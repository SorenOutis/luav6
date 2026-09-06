<?php

namespace App\Console\Commands;

use App\Models\Badge;
use App\Support\BadgeImageGenerator;
use Illuminate\Console\Command;

class GenerateBadgeImages extends Command
{
    protected $signature = 'badges:generate-images
        {--update-records : Update existing level badge records with the generated image paths}';

    protected $description = 'Generate the level 1-100 badge images under public/images/badges';

    public function handle(): int
    {
        $count = BadgeImageGenerator::generateAll(
            fn (int $level, int $index) => $this->line(sprintf(
                'Generated [%03d/100] badge/level-%03d.svg',
                $index,
                $level
            ))
        );

        $this->info(sprintf('Generated %d badge images on the public disk.', $count));

        if ($this->option('update-records')) {
            $updated = 0;

            foreach (range(BadgeImageGenerator::MIN_LEVEL, BadgeImageGenerator::MAX_LEVEL) as $level) {
                $updated += Badge::withoutGlobalScopes()
                    ->where('required_level', $level)
                    ->update(['image_path' => BadgeImageGenerator::filenameFor($level)]);
            }

            $this->info(sprintf('Linked %d existing badge records to the generated images.', $updated));
        }

        return self::SUCCESS;
    }
}
