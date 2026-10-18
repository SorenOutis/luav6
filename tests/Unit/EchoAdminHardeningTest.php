<?php

use App\Ai\Agents\AdminAssistantAgent;

it('loads write tools only for the matching intent', function () {
    $agent = (new AdminAssistantAgent)->setIntent('disable maintenance');
    $names = collect($agent->tools())->map(fn ($tool) => $tool->name())->all();

    expect($names)->toContain('platform_snapshot')
        ->toContain('manage_maintenance')
        ->not->toContain('create_exam');
});

it('keeps every write tool when the intent does not match a group', function () {
    $agent = (new AdminAssistantAgent)->setIntent('what can you do');
    $names = collect($agent->tools())->map(fn ($tool) => $tool->name());

    expect($names)->toContain('create_exam')->toContain('manage_maintenance');
});
