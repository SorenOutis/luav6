<?php

/**
 * Test script to verify AIService essay grading works with the configured AI provider.
 * Run with: php test_essay_grading.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\Setting;
use App\Services\AIService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

echo "═══════════════════════════════════════\n";
echo "  AI Essay Grading Test (with debug)\n";
echo "═══════════════════════════════════════\n\n";

// 1. Check current provider settings
$provider = Setting::get('ai_provider', 'not set');
$ollamaEnabled = Setting::get('ollama_enabled', false);
echo "AI Provider: {$provider}\n";
echo 'Ollama Fallback: '.($ollamaEnabled ? 'enabled' : 'disabled')."\n";

// Log settings
$cfAccountId = Setting::get('cloudflare_account_id');
$cfToken = Setting::get('cloudflare_api_token');
$cfModel = Setting::get('cloudflare_grading_model', '@cf/meta/llama-3.1-8b-instruct');
echo "Cloudflare Model: {$cfModel}\n\n";

// 2. Test: Call the Cloudflare API DIRECTLY to see raw response
echo "═══ Step 1: Direct Cloudflare API call (raw response test) ═══\n\n";

$systemPrompt = 'You are a strict academic examiner. Always respond with valid JSON only.';
$userPrompt = <<<'PROMPT'
Act as a STRICT academic examiner. Your task is to evaluate a student's essay response based on a specific question.

Question: "Explain the water cycle and its importance to life on Earth."
Student Essay: "The water cycle, also known as the hydrologic cycle, describes the continuous movement of water on, above, and below the surface of the Earth. It begins with evaporation, where the sun heats water in oceans, lakes, and rivers, turning it into water vapor. This vapor rises into the atmosphere, where it cools and condenses to form clouds through a process called condensation. When the clouds become heavy enough, the water falls back to Earth as precipitation—rain, snow, sleet, or hail. The water then collects in bodies of water or seeps into the ground, and the cycle begins again. This cycle is essential for distributing fresh water across the planet and supporting all forms of life."

STRICT GRADING RULES:
1. COMPREHENSIVENESS: The answer MUST be comprehensive and thorough.
2. RELEVANCE: The answer MUST be directly related to the question.
3. FACTUAL ACCURACY: Points should only be awarded for correct facts.
4. MINIMUM SUBSTANCE: Very short answers get low scores.

SCORING CRITERIA (0-100 SCALE):
- 100: Comprehensive, highly relevant, and accurate.
- 70-90: Relevant but lacks some depth.
- 40-60: Relevant but superficial.
- 10-30: Barely relevant or very short.
- 0: Irrelevant, nonsensical, or "I don't know".

You MUST respond with a valid JSON object ONLY.
The score MUST be a WHOLE NUMBER between 0 and 100.

{
    "score": <integer_value_between_0_and_100>,
    "feedback": "<short_actionable_feedback>"
}
PROMPT;

$response = Http::withToken($cfToken)
    ->timeout(45)
    ->post("https://api.cloudflare.com/client/v4/accounts/{$cfAccountId}/ai/run/{$cfModel}", [
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ],
    ]);

if ($response->successful()) {
    $rawData = $response->json();
    $rawText = $rawData['result']['response'] ?? $rawData['response'] ?? '(no response field)';
    echo "Cloudflare raw response text:\n";
    echo "──────────────────────────────\n";
    echo $rawText."\n";
    echo "──────────────────────────────\n\n";

    $parsed = json_decode($rawText, true);
    if ($parsed) {
        echo "Parsed JSON: score={$parsed['score']}, feedback={$parsed['feedback']}\n\n";
    } else {
        echo "⚠ Could not parse as JSON\n\n";
    }
} else {
    echo '❌ Cloudflare API error: '.$response->status().' - '.$response->body()."\n\n";
}

echo "═══ Step 2: Full AIService batch test ═══\n\n";

$aiService = app(AIService::class);

$essays = [
    [
        'essayText' => 'The water cycle, also known as the hydrologic cycle, describes the continuous movement of water on, above, and below the surface of the Earth. It begins with evaporation, where the sun heats water in oceans, lakes, and rivers, turning it into water vapor. This vapor rises into the atmosphere, where it cools and condenses to form clouds through a process called condensation. When the clouds become heavy enough, the water falls back to Earth as precipitation—rain, snow, sleet, or hail. The water then collects in bodies of water or seeps into the ground, and the cycle begins again. This cycle is essential for distributing fresh water across the planet and supporting all forms of life.',
        'questionText' => 'Explain the water cycle and its importance to life on Earth.',
        'maxPoints' => 5,
        'includeFeedback' => true,
    ],
    [
        'essayText' => 'I do not know the answer to this question. Skip it please.',
        'questionText' => 'Describe the process of photosynthesis in plants.',
        'maxPoints' => 5,
        'includeFeedback' => true,
    ],
];

echo "Starting AIService::batchAssessEssays()...\n";

$start = microtime(true);

try {
    $results = $aiService->batchAssessEssays($essays);
    $elapsed = round(microtime(true) - $start, 2);

    echo "Completed in {$elapsed}s\n\n";

    foreach ($results as $index => $result) {
        echo '── Essay #'.($index + 1)." ──────────────\n";
        echo "  Score:    {$result['score']} / {$essays[$index]['maxPoints']}\n";
        echo '  Feedback: '.($result['feedback'] ?? '(none)')."\n\n";
    }

    // Validation
    $pass = true;
    if ($results[0]['score'] <= 0) {
        echo "⚠ Essay 1 scored 0 — comprehensive answer should score > 0\n";
        $pass = false;
    } else {
        echo "✅ Essay 1 correctly scored > 0\n";
    }
    if ($results[1]['score'] > 2) {
        echo "⚠ Essay 2 scored {$results[1]['score']} — 'I don\\'t know' should be near 0\n";
        $pass = false;
    } else {
        echo "✅ Essay 2 correctly scored near 0\n";
    }

    echo "\n".($pass ? '✅ ALL CHECKS PASSED' : '⚠ Some checks failed')."\n";

} catch (Exception $e) {
    echo '❌ ERROR: '.$e->getMessage()."\n";
    exit(1);
}
