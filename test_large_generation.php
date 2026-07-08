<?php

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\Setting;
use App\Services\AiQuestionGeneratorService;
use Illuminate\Contracts\Console\Kernel;

echo "=== Current AI Provider ===\n";
echo 'Provider: '.Setting::get('ai_provider', 'not set')."\n";
echo 'Cloudflare model: '.Setting::get('cloudflare_model', '@cf/meta/llama-3.1-8b-instruct')."\n\n";

$service = app(AiQuestionGeneratorService::class);

// Large counts: 30 per type
$counts = [
    'multiple_choice' => 30,
    'true_false' => 30,
    'identification' => 30,
    'essay' => 10,
];
$total = array_sum($counts);
echo "=== Requested Counts ===\n";
echo "Multiple Choice: {$counts['multiple_choice']}\n";
echo "True/False:      {$counts['true_false']}\n";
echo "Identification:  {$counts['identification']}\n";
echo "Essay:           {$counts['essay']}\n";
echo "Total:           {$total}\n\n";

// A decent chunk of source material to generate questions from
$sourceText = <<<'TEXT'
The Water Cycle

Water is constantly moving through the Earth's atmosphere, oceans, and land in a process called the water cycle (also known as the hydrologic cycle). This continuous movement is driven by energy from the sun and gravity.

Key Processes:

1. Evaporation: When the sun heats water in rivers, lakes, and oceans, it turns into water vapor (a gas) and rises into the atmosphere. About 90% of atmospheric water comes from evaporation from oceans.

2. Transpiration: Plants absorb water through their roots and release water vapor through tiny pores in their leaves called stomata. This process is called transpiration. Together, evaporation and transpiration are called evapotranspiration.

3. Condensation: As water vapor rises, it cools and changes back into liquid water droplets, forming clouds. This process is called condensation. Condensation requires surfaces like dust particles or pollen for the water to condense onto (called condensation nuclei).

4. Precipitation: When water droplets in clouds become heavy enough, they fall to the Earth as precipitation. This can be rain, snow, sleet, or hail, depending on the temperature of the atmosphere.

5. Infiltration: Some of the precipitation that falls on land soaks into the ground, a process called infiltration. The water moves downward through soil and rock layers until it reaches the water table.

6. Runoff: Water that does not infiltrate the ground flows across the surface as runoff, eventually making its way into streams, rivers, and oceans. Runoff can pick up pollutants and carry them into water bodies.

7. Sublimation: In very cold conditions, ice and snow can change directly into water vapor without first melting into liquid. This is called sublimation.

The water cycle has no beginning or end - it is a continuous loop. The amount of water on Earth remains relatively constant, with about 97% in oceans, 2% frozen in ice caps and glaciers, and about 1% as freshwater available for human use.

Water Cycle Reservoirs:
- Oceans: ~1,338,000,000 cubic kilometers (96.5%)
- Ice caps and glaciers: ~24,064,000 cubic kilometers (1.74%)
- Groundwater: ~23,400,000 cubic kilometers (1.69%)
- Lakes and rivers: ~178,000 cubic kilometers (0.013%)
- Atmosphere: ~12,900 cubic kilometers (0.001%)

Human Impact: Human activities affect the water cycle through deforestation (reduces transpiration), urbanization (increases runoff and reduces infiltration), and greenhouse gas emissions (alter precipitation patterns and increase evaporation rates).

Environmental Importance: The water cycle is essential for all life on Earth. It distributes heat around the planet, transports nutrients, shapes landscapes through erosion, and provides fresh water for drinking, agriculture, and industry.
TEXT;

echo "=== Source Material ===\n";
echo 'Length: '.strlen($sourceText)." characters\n";
echo str_repeat('-', 60)."\n\n";

echo "Calling AI to generate {$total} questions...\n\n";
$start = microtime(true);

try {
    $questions = $service->generate($sourceText, $counts, 'medium');

    $elapsed = round(microtime(true) - $start, 2);

    echo "=== RESULTS ===\n";
    echo "Time elapsed: {$elapsed}s\n";
    echo 'Total questions returned: '.count($questions)."\n\n";

    // Count by type
    $typeCounts = ['multiple_choice' => 0, 'true_false' => 0, 'identification' => 0, 'essay' => 0];
    foreach ($questions as $q) {
        $type = $q['type'] ?? 'unknown';
        if (isset($typeCounts[$type])) {
            $typeCounts[$type]++;
        }
    }

    echo "By type:\n";
    foreach ($typeCounts as $type => $count) {
        $requested = $counts[$type] ?? 0;
        $pct = $requested > 0 ? round($count / $requested * 100) : 0;
        echo "  {$type}: {$count}/{$requested} ({$pct}%)\n";
    }
    echo "\n";

    // Show samples of each type
    echo "=== SAMPLES ===\n";
    $seenTypes = [];
    foreach ($questions as $i => $q) {
        $type = $q['type'] ?? '';
        if (! isset($seenTypes[$type])) {
            $seenTypes[$type] = true;
            echo "\n--- {$type} #".($i + 1)." ---\n";
            echo 'Text: '.mb_substr($q['text'] ?? '', 0, 100)."...\n";
            if (! empty($q['options'])) {
                $correctCount = 0;
                foreach ($q['options'] as $opt) {
                    if (! empty($opt['is_correct'])) {
                        $correctCount++;
                    }
                }
                echo 'Options: '.count($q['options'])." (correct: {$correctCount})\n";
                echo 'First option: '.mb_substr($q['options'][0]['text'] ?? '', 0, 60)."...\n";
            }
            if (! empty($q['correct_answer'])) {
                echo 'Answer: '.mb_substr($q['correct_answer'] ?? '', 0, 60)."\n";
            }
        }
    }

    $allValid = true;
    foreach ($questions as $i => $q) {
        $type = $q['type'] ?? '';
        $text = trim($q['text'] ?? '');
        if (empty($text) || ! in_array($type, ['multiple_choice', 'true_false', 'identification', 'essay'])) {
            echo "\nWARNING: Question #".($i + 1)." has invalid data!\n";
            $allValid = false;
        }
        if (in_array($type, ['multiple_choice', 'true_false'])) {
            $opts = $q['options'] ?? [];
            if (count($opts) < 2) {
                echo "\nWARNING: Question #".($i + 1)." ({$type}) has fewer than 2 options!\n";
                $allValid = false;
            }
        }
        if ($type === 'identification' && empty($q['correct_answer'] ?? '')) {
            echo "\nWARNING: Question #".($i + 1)." (identification) has no correct answer!\n";
            $allValid = false;
        }
    }

    echo "\n=== VERDICT ===\n";
    if (count($questions) > 0) {
        echo '✅ SUCCESS: Generated '.count($questions)." questions\n";
        if ($allValid) {
            echo "✅ All questions have valid structure\n";
        }
    } else {
        echo "❌ FAILED: No questions returned\n";
    }

} catch (Throwable $e) {
    $elapsed = round(microtime(true) - $start, 2);
    echo "❌ FAILED after {$elapsed}s: ".$e->getMessage()."\n";
    echo 'File: '.$e->getFile().':'.$e->getLine()."\n";
}
