<?php

use App\Ai\Agents\AdminAssistantAgent;
use App\Ai\Skills\AdminAssistantSkill;

it('loads the committed admin assistant skill', function () {
    $skill = AdminAssistantSkill::instructions();

    expect($skill)
        ->toContain('# AI Admin Panel and Report Export Skill')
        ->toContain('Do not claim that an Excel, Word, or PDF file was created')
        ->toContain('approval_required');
});

it('includes the runtime skill in admin assistant instructions', function () {
    $instructions = (string) (new AdminAssistantAgent)->instructions();

    expect($instructions)
        ->toContain('RUNTIME SKILL:')
        ->toContain('Expected export feature contract')
        ->toContain('Never invent student, section, course, exam, submission, or grade IDs.');
});

it('exposes the approved report export tool', function () {
    $toolNames = collect((new AdminAssistantAgent)->tools())
        ->map(fn ($tool): string => $tool->name())
        ->all();

    expect($toolNames)->toContain('export_report');
});
