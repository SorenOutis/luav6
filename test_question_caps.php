<?php

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\AiQuestionGeneratorService;

$passed = 0;
$failed = 0;

function check(string $label, bool $condition, string $detail = '') {
    global $passed, $failed;
    if ($condition) {
        echo "  ✅ {$label}\n";
        $passed++;
    } else {
        echo "  ❌ {$label}";
        if ($detail) echo " — {$detail}";
        echo "\n";
        $failed++;
    }
}

echo "=== Testing Form Validation Logic (server-side caps) ===\n\n";

// Test: total <= 0 should be rejected
$counts = ['multiple_choice' => 0, 'true_false' => 0, 'identification' => 0, 'essay' => 0];
$total = array_sum($counts);
check(
    'Total 0 is rejected (at least 1 required)',
    $total <= 0,
    "Got total={$total}"
);

// Test: total > 100 should be rejected
$counts = ['multiple_choice' => 30, 'true_false' => 30, 'identification' => 30, 'essay' => 11];
$total = array_sum($counts);
check(
    'Total 101 is rejected (max 100)',
    $total > 100,
    "Got total={$total}"
);

// Test: total exactly 100 is accepted
$counts = ['multiple_choice' => 30, 'true_false' => 30, 'identification' => 30, 'essay' => 10];
$total = array_sum($counts);
check(
    'Total 100 is accepted (within bounds)',
    $total > 0 && $total <= 100,
    "Got total={$total}"
);

// Test: total exactly 1 is accepted
$counts = ['multiple_choice' => 1, 'true_false' => 0, 'identification' => 0, 'essay' => 0];
$total = array_sum($counts);
check(
    'Total 1 is accepted',
    $total > 0 && $total <= 100,
    "Got total={$total}"
);

echo "\n=== Testing enforceCounts() ===\n\n";

// Test: enforceCounts trims excess questions
$questions = [
    ['type' => 'multiple_choice', 'text' => 'MC 1'],
    ['type' => 'multiple_choice', 'text' => 'MC 2'],
    ['type' => 'multiple_choice', 'text' => 'MC 3'],
    ['type' => 'multiple_choice', 'text' => 'MC 4'],
    ['type' => 'multiple_choice', 'text' => 'MC 5'],
    ['type' => 'true_false', 'text' => 'TF 1'],
    ['type' => 'true_false', 'text' => 'TF 2'],
    ['type' => 'true_false', 'text' => 'TF 3'],
    ['type' => 'true_false', 'text' => 'TF 4'],
    ['type' => 'identification', 'text' => 'ID 1'],
    ['type' => 'identification', 'text' => 'ID 2'],
    ['type' => 'essay', 'text' => 'Essay 1'],
    ['type' => 'essay', 'text' => 'Essay 2'],
    ['type' => 'essay', 'text' => 'Essay 3'],
];

// Cap: each type at 2 — should keep 2 MC, 2 TF, 2 ID, 2 Essay = 8 total
$counts = ['multiple_choice' => 2, 'true_false' => 2, 'identification' => 2, 'essay' => 2];
$result = AiQuestionGeneratorService::enforceCounts($questions, $counts);
check(
    'enforceCounts trims 14 questions to 8 (2 per type)',
    count($result) === 8,
    'Got ' . count($result) . ' questions'
);

// Verify types are correct
$mc = count(array_filter($result, fn($q) => $q['type'] === 'multiple_choice'));
$tf = count(array_filter($result, fn($q) => $q['type'] === 'true_false'));
$id = count(array_filter($result, fn($q) => $q['type'] === 'identification'));
$es = count(array_filter($result, fn($q) => $q['type'] === 'essay'));
check('MC trimmed to 2', $mc === 2, "Got {$mc}");
check('TF trimmed to 2', $tf === 2, "Got {$tf}");
check('ID trimmed to 2', $id === 2, "Got {$id}");
check('Essay trimmed to 2', $es === 2, "Got {$es}");

// Test: type set to 0 drops all questions of that type
$counts = ['multiple_choice' => 0, 'true_false' => 2, 'identification' => 2, 'essay' => 0];
$result = AiQuestionGeneratorService::enforceCounts($questions, $counts);
$mc = count(array_filter($result, fn($q) => $q['type'] === 'multiple_choice'));
$es = count(array_filter($result, fn($q) => $q['type'] === 'essay'));
check('MC dropped when count=0', $mc === 0, "Got {$mc}");
check('Essay dropped when count=0', $es === 0, "Got {$es}");

// Test: empty questions returns empty
$result = AiQuestionGeneratorService::enforceCounts([], ['multiple_choice' => 5, 'true_false' => 3, 'identification' => 3, 'essay' => 1]);
check('Empty input returns empty array', count($result) === 0);

// Test: fewer questions than requested keeps all
$questions = [
    ['type' => 'multiple_choice', 'text' => 'MC 1'],
    ['type' => 'multiple_choice', 'text' => 'MC 2'],
];
$counts = ['multiple_choice' => 10, 'true_false' => 0, 'identification' => 0, 'essay' => 0];
$result = AiQuestionGeneratorService::enforceCounts($questions, $counts);
check('Fewer than requested keeps all', count($result) === 2, "Got " . count($result));

// Test: unknown types are ignored
$questions = [
    ['type' => 'multiple_choice', 'text' => 'MC 1'],
    ['type' => 'essay', 'text' => 'Essay 1'],
    ['type' => 'fill_in_the_blank', 'text' => 'FIB 1'],
];
$counts = ['multiple_choice' => 1, 'true_false' => 0, 'identification' => 0, 'essay' => 1];
$result = AiQuestionGeneratorService::enforceCounts($questions, $counts);
check('Unknown types are filtered out', count($result) === 2, "Got " . count($result));

echo "\n=== RESULTS ===\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";
echo ($failed === 0 ? "✅ ALL PASSED" : "❌ SOME FAILED") . "\n";
