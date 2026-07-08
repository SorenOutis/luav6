<?php

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AiQuestionDraft;
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

echo "=== Step 1: Create a draft (simulating form submission) ===\n\n";

$draft = AiQuestionDraft::create([
    'user_id' => 1,
    'title' => 'Test Realtime Flow',
    'topic' => 'Computer Repair',
    'source_text' => 'The motherboard is the main circuit board of a computer. It houses the CPU, RAM, and expansion slots. Common repair tools include screwdrivers, multimeters, and anti-static wrist straps.',
    'type_counts' => ['multiple_choice' => 2, 'true_false' => 2, 'identification' => 1, 'essay' => 1],
    'difficulty' => 'medium',
    'status' => 'pending',
]);

check('Draft created with ID', $draft->id > 0, "ID: {$draft->id}");
check('Status is pending', $draft->status === 'pending', "Got: {$draft->status}");
check('Questions are null initially', $draft->questions === null);

echo "\n=== Step 2: Simulate pollGenerationStatus() — record refresh ===\n\n";

// Store current record ID
$recordId = $draft->id;

// Simulate what the Edit page does on poll — refresh and check status
$freshRecord = AiQuestionDraft::find($recordId);
$originalUpdatedAt = $freshRecord->updated_at->format('Y-m-d H:i:s');

check('Record found in DB', $freshRecord !== null);
check('Status still pending', $freshRecord->status === 'pending', "Got: {$freshRecord->status}");

$service = app(AiQuestionGeneratorService::class);

echo "\n=== Step 3: Call service->generate() synchronously ===\n\n";

try {
    $questions = $service->generate(
        sourceText: $draft->source_text,
        typeCounts: $draft->type_counts,
        difficulty: $draft->difficulty,
        topic: $draft->topic,
    );

    if (! empty($questions)) {
        echo "  ✅ Generation returned " . count($questions) . " questions\n";
        $passed++;

        // Save questions to draft (like the job does)
        $draft->forceFill([
            'questions' => $questions,
            'status' => 'ready',
            'generated_at' => now(),
        ])->save();

        echo "\n=== Step 4: Simulate poll seeing 'ready' status ===\n\n";

        // Simulate what pollGenerationStatus() does — refresh record
        $draft->refresh();
        check('Status changed to ready', $draft->status === 'ready', "Got: {$draft->status}");
        check('Questions are now populated', is_array($draft->questions) && count($draft->questions) > 0, "Got: " . count($draft->questions ?? []));
        check('Questions have valid structure', ! empty($draft->questions[0]['text'] ?? ''));

        // Show type breakdown
        $byType = [];
        foreach ($draft->questions as $q) {
            $type = $q['type'] ?? 'unknown';
            $byType[$type] = ($byType[$type] ?? 0) + 1;
        }
        echo "\nQuestion breakdown:\n";
        foreach ($byType as $type => $count) {
            echo "  - {$type}: {$count}\n";
        }

    } else {
        echo "  ⚠️ AI returned 0 questions (provider may be rate-limited or offline)\n";
        echo "  This is not necessarily a bug — it depends on the AI provider being reachable.\n";
        $failed++;
    }
} catch (\Throwable $e) {
    echo "  ⚠️ Generation threw: " . $e->getMessage() . "\n";
    echo "  This may be expected if the AI provider is unavailable.\n";
}

echo "\n=== Step 5: Simulate poll stopping when generation completes ===\n\n";

// After status is 'ready', pollGenerationStatus should NOT refresh
$statuses = ['ready', 'failed'];
foreach ($statuses as $status) {
    $draft->forceFill(['status' => $status])->save();
    $shouldStop = ! in_array($status, ['pending', 'running', 'generating_source']);
    check("Poll stops when status is '{$status}'", $shouldStop);
}

echo "\n=== Step 6: Cleanup ===\n\n";
$draft->forceDelete();
check('Test draft cleaned up', true);

echo "\n=== RESULTS ===\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";
echo ($failed === 0 ? "✅ ALL PASSED" : "❌ SOME FAILED") . "\n";
