<?php

use App\Ai\Agents\AdminAssistantAgent;
use App\Ai\Agents\AssistantAgent;
use App\Support\PlatformKnowledge;

it('provides student platform knowledge notes with core mechanics', function () {
    $notes = PlatformKnowledge::studentNotes();

    expect($notes)
        ->toContain('PLATFORM KNOWLEDGE BASE')
        ->toContain('Total XP')
        ->toContain('Daily XP Claim')
        ->toContain('Streaks')
        ->toContain('Seasons');
});

it('provides admin platform architecture notes with policy details', function () {
    $notes = PlatformKnowledge::adminNotes();

    expect($notes)
        ->toContain('PLATFORM ARCHITECTURE & WORKSPACE KNOWLEDGE BASE')
        ->toContain('HUMAN-IN-THE-LOOP SAFETY PATTERN')
        ->toContain('PendingAiAction')
        ->toContain('PLATFORM MAINTENANCE MODE');
});

it('injects platform notes into AssistantAgent instructions', function () {
    $agent = new AssistantAgent;
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('PLATFORM KNOWLEDGE BASE (LSI LEARNING PLATFORM)')
        ->toContain('XP NEVER decreases when you buy rewards');
});

it('injects platform architecture notes into AdminAssistantAgent instructions', function () {
    $agent = new AdminAssistantAgent;
    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('PLATFORM ARCHITECTURE & WORKSPACE KNOWLEDGE BASE')
        ->toContain('HUMAN-IN-THE-LOOP SAFETY PATTERN (CRITICAL)');
});
