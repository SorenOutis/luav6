<?php

use App\Http\Controllers\ChatController;

/**
 * Tests for the server-side toxicity guardrail in ChatController.
 *
 * Verifies that the isToxic() and normalizeMessage() private methods
 * correctly detect profanity, insults, harassment, and creative spellings/leetspeak.
 */

// ─── normalizeMessage tests ─────────────────────────────────

test('normalizeMessage converts leetspeak 1 → i', function () {
    $controller = new ChatController;
    $method = new ReflectionMethod(ChatController::class, 'normalizeMessage');
    $method->setAccessible(true);

    $result = $method->invoke($controller, 'sh1t');

    expect($result)->toBe('shit');
});

test('normalizeMessage converts leetspeak 4 → a', function () {
    $controller = new ChatController;
    $method = new ReflectionMethod(ChatController::class, 'normalizeMessage');
    $method->setAccessible(true);

    $result = $method->invoke($controller, 'b4stard');

    expect($result)->toBe('bastard');
});

test('normalizeMessage converts leetspeak @ → a', function () {
    $controller = new ChatController;
    $method = new ReflectionMethod(ChatController::class, 'normalizeMessage');
    $method->setAccessible(true);

    $result = $method->invoke($controller, 'b@stard');

    expect($result)->toBe('bastard');
});

test('normalizeMessage converts leetspeak 5 → s', function () {
    $controller = new ChatController;
    $method = new ReflectionMethod(ChatController::class, 'normalizeMessage');
    $method->setAccessible(true);

    $result = $method->invoke($controller, '5hit');

    expect($result)->toBe('shit');
});

test('normalizeMessage converts leetspeak 0 → o', function () {
    $controller = new ChatController;
    $method = new ReflectionMethod(ChatController::class, 'normalizeMessage');
    $method->setAccessible(true);

    $result = $method->invoke($controller, 'hell0');

    expect($result)->toBe('hello');
});

test('normalizeMessage converts leetspeak 3 → e', function () {
    $controller = new ChatController;
    $method = new ReflectionMethod(ChatController::class, 'normalizeMessage');
    $method->setAccessible(true);

    $result = $method->invoke($controller, 'cr3ap');

    expect($result)->toBe('creap');
});

test('normalizeMessage converts $ → s', function () {
    $controller = new ChatController;
    $method = new ReflectionMethod(ChatController::class, 'normalizeMessage');
    $method->setAccessible(true);

    $result = $method->invoke($controller, '$hit');

    expect($result)->toBe('shit');
});

test('normalizeMessage converts multiple leetspeak chars in one message', function () {
    $controller = new ChatController;
    $method = new ReflectionMethod(ChatController::class, 'normalizeMessage');
    $method->setAccessible(true);

    $result = $method->invoke($controller, 'sh1t 1s n0t g00d');

    expect($result)->toBe('shit is not good');
});

test('normalizeMessage handles mixed case message', function () {
    $controller = new ChatController;
    $method = new ReflectionMethod(ChatController::class, 'normalizeMessage');
    $method->setAccessible(true);

    $result = $method->invoke($controller, 'What 4 sh1tty d4y');

    expect($result)->toBe('What a shitty day');
});

// ─── isToxic tests ───────────────────────────────────────────

test('isToxic detects basic swear words', function () {
    $controller = new ChatController;
    $method = new ReflectionMethod(ChatController::class, 'isToxic');
    $method->setAccessible(true);

    expect($method->invoke($controller, 'fuck this'))->toBeTrue();
    expect($method->invoke($controller, 'bullshit'))->toBeTrue();
    expect($method->invoke($controller, 'asshole'))->toBeTrue();
    expect($method->invoke($controller, 'bitch'))->toBeTrue();
    expect($method->invoke($controller, 'cunt'))->toBeTrue();
});

test('isToxic detects abbreviations', function () {
    $controller = new ChatController;
    $method = new ReflectionMethod(ChatController::class, 'isToxic');
    $method->setAccessible(true);

    expect($method->invoke($controller, 'wtf'))->toBeTrue();
    expect($method->invoke($controller, 'stfu'))->toBeTrue();
    expect($method->invoke($controller, 'fkn'))->toBeTrue();
    expect($method->invoke($controller, 'kys'))->toBeTrue();
});

test('isToxic detects insults', function () {
    $controller = new ChatController;
    $method = new ReflectionMethod(ChatController::class, 'isToxic');
    $method->setAccessible(true);

    expect($method->invoke($controller, 'you are stupid'))->toBeTrue();
    expect($method->invoke($controller, 'you idiot'))->toBeTrue();
    expect($method->invoke($controller, 'dumb'))->toBeTrue();
    expect($method->invoke($controller, 'loser'))->toBeTrue();
    expect($method->invoke($controller, 'retard'))->toBeTrue();
});

test('isToxic detects harassment', function () {
    $controller = new ChatController;
    $method = new ReflectionMethod(ChatController::class, 'isToxic');
    $method->setAccessible(true);

    expect($method->invoke($controller, 'stop bullying'))->toBeTrue();
    expect($method->invoke($controller, 'harass'))->toBeTrue();
    expect($method->invoke($controller, 'racist'))->toBeTrue();
    expect($method->invoke($controller, 'sexist'))->toBeTrue();
});

test('isToxic detects leetspeak creative spellings', function () {
    $controller = new ChatController;
    $method = new ReflectionMethod(ChatController::class, 'isToxic');
    $method->setAccessible(true);

    expect($method->invoke($controller, 'sh1t'))->toBeTrue();
    expect($method->invoke($controller, 'b@stard'))->toBeTrue();
    expect($method->invoke($controller, 'd1ck'))->toBeTrue();
    expect($method->invoke($controller, 'cr4p'))->toBeTrue();
    expect($method->invoke($controller, '$lut'))->toBeTrue();
    expect($method->invoke($controller, '5h1t'))->toBeTrue();
    expect($method->invoke($controller, 'fck'))->toBeTrue();
});

test('isToxic allows clean academic messages', function () {
    $controller = new ChatController;
    $method = new ReflectionMethod(ChatController::class, 'isToxic');
    $method->setAccessible(true);

    expect($method->invoke($controller, 'What are my upcoming assignments?'))->toBeFalse();
    expect($method->invoke($controller, 'Can you help me with my math homework?'))->toBeFalse();
    expect($method->invoke($controller, 'Show me my learning progress'))->toBeFalse();
    expect($method->invoke($controller, 'What exams do I have coming up?'))->toBeFalse();
    expect($method->invoke($controller, 'Hello, how are you?'))->toBeFalse();
    expect($method->invoke($controller, 'Thank you for your help'))->toBeFalse();
});

test('isToxic does not flag innocent words with substring matches', function () {
    $controller = new ChatController;
    $method = new ReflectionMethod(ChatController::class, 'isToxic');
    $method->setAccessible(true);

    // These words contain swear substrings but shouldn't match due to word boundaries
    expect($method->invoke($controller, 'classification'))->toBeFalse();
    expect($method->invoke($controller, 'mouse'))->toBeFalse();
    expect($method->invoke($controller, 'assumption'))->toBeFalse();
    expect($method->invoke($controller, 'cocktail recipe for my home economics class'))->toBeFalse();
    expect($method->invoke($controller, 'I need help understanding photosynthesis'))->toBeFalse();
});

test('isToxic catches compound/derived words via sloppy pattern', function () {
    $controller = new ChatController;
    $method = new ReflectionMethod(ChatController::class, 'isToxic');
    $method->setAccessible(true);

    expect($method->invoke($controller, 'fucking'))->toBeTrue();
    expect($method->invoke($controller, 'motherfucker'))->toBeTrue();
    expect($method->invoke($controller, 'fucked'))->toBeTrue();
    expect($method->invoke($controller, 'fucker'))->toBeTrue();
});

test('isToxic detects profanity in a longer sentence', function () {
    $controller = new ChatController;
    $method = new ReflectionMethod(ChatController::class, 'isToxic');
    $method->setAccessible(true);

    expect($method->invoke($controller, 'This is such a bullshit assignment'))->toBeTrue();
    expect($method->invoke($controller, 'You are a dumb teacher'))->toBeTrue();
    expect($method->invoke($controller, 'I hate this fucking class'))->toBeTrue();
});
