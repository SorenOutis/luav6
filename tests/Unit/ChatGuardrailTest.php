<?php

use App\Services\AiChatLogger;
use App\Services\ChatService;

/**
 * Tests for the server-side toxicity guardrail in ChatService.
 *
 * Verifies that the isToxic() and normalizeMessage() methods correctly
 * detect profanity, insults, harassment, and creative spellings/leetspeak.
 */

// ─── normalizeMessage tests ─────────────────────────────────

test('normalizeMessage converts leetspeak 1 → i', function () {
    $service = new ChatService(new AiChatLogger);
    $method = new ReflectionMethod(ChatService::class, 'normalizeMessage');

    $result = $method->invoke($service, 'sh1t');

    expect($result)->toBe('shit');
});

test('normalizeMessage converts leetspeak 4 → a', function () {
    $service = new ChatService(new AiChatLogger);
    $method = new ReflectionMethod(ChatService::class, 'normalizeMessage');

    $result = $method->invoke($service, 'b4stard');

    expect($result)->toBe('bastard');
});

test('normalizeMessage converts leetspeak @ → a', function () {
    $service = new ChatService(new AiChatLogger);
    $method = new ReflectionMethod(ChatService::class, 'normalizeMessage');

    $result = $method->invoke($service, 'b@stard');

    expect($result)->toBe('bastard');
});

test('normalizeMessage converts leetspeak 5 → s', function () {
    $service = new ChatService(new AiChatLogger);
    $method = new ReflectionMethod(ChatService::class, 'normalizeMessage');

    $result = $method->invoke($service, '5hit');

    expect($result)->toBe('shit');
});

test('normalizeMessage converts leetspeak 0 → o', function () {
    $service = new ChatService(new AiChatLogger);
    $method = new ReflectionMethod(ChatService::class, 'normalizeMessage');

    $result = $method->invoke($service, 'hell0');

    expect($result)->toBe('hello');
});

test('normalizeMessage converts leetspeak 3 → e', function () {
    $service = new ChatService(new AiChatLogger);
    $method = new ReflectionMethod(ChatService::class, 'normalizeMessage');

    $result = $method->invoke($service, 'cr3ap');

    expect($result)->toBe('creap');
});

test('normalizeMessage converts $ → s', function () {
    $service = new ChatService(new AiChatLogger);
    $method = new ReflectionMethod(ChatService::class, 'normalizeMessage');

    $result = $method->invoke($service, '$hit');

    expect($result)->toBe('shit');
});

test('normalizeMessage converts multiple leetspeak chars in one message', function () {
    $service = new ChatService(new AiChatLogger);
    $method = new ReflectionMethod(ChatService::class, 'normalizeMessage');

    $result = $method->invoke($service, 'sh1t 1s n0t g00d');

    expect($result)->toBe('shit is not good');
});

test('normalizeMessage handles mixed case message', function () {
    $service = new ChatService(new AiChatLogger);
    $method = new ReflectionMethod(ChatService::class, 'normalizeMessage');

    $result = $method->invoke($service, 'What 4 sh1tty d4y');

    expect($result)->toBe('What a shitty day');
});

// ─── isToxic tests ───────────────────────────────────────────

test('isToxic detects basic swear words', function () {
    $service = new ChatService(new AiChatLogger);
    $method = new ReflectionMethod(ChatService::class, 'isToxic');

    expect($method->invoke($service, 'fuck this'))->toBeTrue();
    expect($method->invoke($service, 'bullshit'))->toBeTrue();
    expect($method->invoke($service, 'asshole'))->toBeTrue();
    expect($method->invoke($service, 'bitch'))->toBeTrue();
    expect($method->invoke($service, 'cunt'))->toBeTrue();
});

test('isToxic detects abbreviations', function () {
    $service = new ChatService(new AiChatLogger);
    $method = new ReflectionMethod(ChatService::class, 'isToxic');

    expect($method->invoke($service, 'wtf'))->toBeTrue();
    expect($method->invoke($service, 'stfu'))->toBeTrue();
    expect($method->invoke($service, 'fkn'))->toBeTrue();
    expect($method->invoke($service, 'kys'))->toBeTrue();
});

test('isToxic detects insults', function () {
    $service = new ChatService(new AiChatLogger);
    $method = new ReflectionMethod(ChatService::class, 'isToxic');

    expect($method->invoke($service, 'you are stupid'))->toBeTrue();
    expect($method->invoke($service, 'you idiot'))->toBeTrue();
    expect($method->invoke($service, 'dumb'))->toBeTrue();
    expect($method->invoke($service, 'loser'))->toBeTrue();
    expect($method->invoke($service, 'retard'))->toBeTrue();
});

test('isToxic detects harassment', function () {
    $service = new ChatService(new AiChatLogger);
    $method = new ReflectionMethod(ChatService::class, 'isToxic');

    expect($method->invoke($service, 'stop bullying'))->toBeTrue();
    expect($method->invoke($service, 'harass'))->toBeTrue();
    expect($method->invoke($service, 'racist'))->toBeTrue();
    expect($method->invoke($service, 'sexist'))->toBeTrue();
});

test('isToxic detects leetspeak creative spellings', function () {
    $service = new ChatService(new AiChatLogger);
    $method = new ReflectionMethod(ChatService::class, 'isToxic');

    expect($method->invoke($service, 'sh1t'))->toBeTrue();
    expect($method->invoke($service, 'b@stard'))->toBeTrue();
    expect($method->invoke($service, 'd1ck'))->toBeTrue();
    expect($method->invoke($service, 'cr4p'))->toBeTrue();
    expect($method->invoke($service, '$lut'))->toBeTrue();
    expect($method->invoke($service, '5h1t'))->toBeTrue();
    expect($method->invoke($service, 'fck'))->toBeTrue();
});

test('isToxic allows clean academic messages', function () {
    $service = new ChatService(new AiChatLogger);
    $method = new ReflectionMethod(ChatService::class, 'isToxic');

    expect($method->invoke($service, 'What are my upcoming assignments?'))->toBeFalse();
    expect($method->invoke($service, 'Can you help me with my math homework?'))->toBeFalse();
    expect($method->invoke($service, 'Show me my learning progress'))->toBeFalse();
    expect($method->invoke($service, 'What exams do I have coming up?'))->toBeFalse();
    expect($method->invoke($service, 'Hello, how are you?'))->toBeFalse();
    expect($method->invoke($service, 'Thank you for your help'))->toBeFalse();
});

test('isToxic does not flag innocent words with substring matches', function () {
    $service = new ChatService(new AiChatLogger);
    $method = new ReflectionMethod(ChatService::class, 'isToxic');

    // These words contain swear substrings but shouldn't match due to word boundaries
    expect($method->invoke($service, 'classification'))->toBeFalse();
    expect($method->invoke($service, 'mouse'))->toBeFalse();
    expect($method->invoke($service, 'assumption'))->toBeFalse();
    expect($method->invoke($service, 'cocktail recipe for my home economics class'))->toBeFalse();
    expect($method->invoke($service, 'I need help understanding photosynthesis'))->toBeFalse();
});

test('isToxic catches compound/derived words via sloppy pattern', function () {
    $service = new ChatService(new AiChatLogger);
    $method = new ReflectionMethod(ChatService::class, 'isToxic');

    expect($method->invoke($service, 'fucking'))->toBeTrue();
    expect($method->invoke($service, 'motherfucker'))->toBeTrue();
    expect($method->invoke($service, 'fucked'))->toBeTrue();
    expect($method->invoke($service, 'fucker'))->toBeTrue();
});

test('isToxic detects profanity in a longer sentence', function () {
    $service = new ChatService(new AiChatLogger);
    $method = new ReflectionMethod(ChatService::class, 'isToxic');

    expect($method->invoke($service, 'This is such a bullshit assignment'))->toBeTrue();
    expect($method->invoke($service, 'You are a dumb teacher'))->toBeTrue();
    expect($method->invoke($service, 'I hate this fucking class'))->toBeTrue();
});
