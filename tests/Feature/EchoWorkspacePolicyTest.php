<?php

use App\Models\User;
use App\Services\AiPolicyEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('blocks a workspace admin from staging a write in another workspace', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'is_super_admin' => false,
    ]);

    $result = app(AiPolicyEngineService::class)->evaluateAction(
        'update_exam',
        ['workspace_id' => 99, 'duration_minutes' => 60],
        $admin,
        1,
    );

    expect($result->allowed)->toBeFalse();
    expect($result->violations)->toContain('cross_workspace');
});

it('allows a super admin to target another workspace id', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'is_super_admin' => true,
    ]);

    $result = app(AiPolicyEngineService::class)->evaluateAction(
        'update_exam',
        ['workspace_id' => 99, 'duration_minutes' => 60],
        $admin,
        1,
    );

    expect($result->allowed)->toBeTrue();
});
