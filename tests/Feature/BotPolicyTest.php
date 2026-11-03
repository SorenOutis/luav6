<?php

use App\Ai\Support\BotPolicy;
use App\Models\AiBot;
use App\Models\User;
use App\Models\Workspace;

it('locks write mode and workspace-only data access in normalized policy', function () {
    $policy = BotPolicy::normalize([
        'writes' => ['mode' => 'auto_execute'],
        'data_access' => ['workspace_only' => false, 'cross_workspace' => true],
    ]);

    expect($policy['writes']['mode'])->toBe(BotPolicy::WRITE_MODE_APPROVAL_REQUIRED);
    expect($policy['data_access']['workspace_only'])->toBeTrue();
    expect($policy['data_access']['cross_workspace'])->toBeFalse();
});

it('strips write tools from a coordinator on save', function () {
    $workspace = Workspace::factory()->create();
    $admin = User::factory()->superAdmin()->create();
    $admin->forceFill(['current_workspace_id' => $workspace->id])->save();

    $bot = AiBot::factory()->coordinator()->create([
        'workspace_id' => $workspace->id,
        'allowed_tools' => ['students', 'create_exam'],
        'created_by' => $admin->id,
    ]);

    expect($bot->allowed_tools)->not->toContain('create_exam');
    expect($bot->allowed_tools)->toContain('students');
});

it('reports drift for unknown tool keys and missing identity', function () {
    $workspace = Workspace::factory()->create();
    $bot = AiBot::factory()->create([
        'workspace_id' => $workspace->id,
        'allowed_tools' => ['does_not_exist'],
        'policy' => ['identity' => ['role' => '', 'mission' => '']],
    ]);

    $warnings = $bot->driftWarnings();

    expect($warnings)->toContain('Unknown tool keys: does_not_exist.');
    expect($warnings)->toContain('Policy is missing an identity role.');
});

it('derives allowed actions from the tool registry write keys', function () {
    $actions = BotPolicy::from([], 'specialist')->allowedActions(['students', 'create_exam', 'nope']);

    expect($actions)->toBe(['create_exam']);
});
