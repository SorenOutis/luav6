<?php

use App\Ai\Support\AiBotRoster;
use App\Ai\Support\ToolRegistry;
use App\Models\AiBot;
use App\Models\AiBotRoutine;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\AiBotRosterSeeder;

it('seeds one coordinator and eight specialists with the expected slugs', function () {
    $workspace = Workspace::factory()->create();
    User::factory()->superAdmin()->create();

    $this->seed(AiBotRosterSeeder::class);

    $bots = AiBot::query()
        ->withoutGlobalScope('workspace')
        ->where('workspace_id', $workspace->id)
        ->get();

    expect($bots)->toHaveCount(9)
        ->and($bots->where('kind', AiBot::KIND_COORDINATOR))->toHaveCount(1)
        ->and($bots->where('kind', AiBot::KIND_SPECIALIST))->toHaveCount(8)
        ->and($bots->pluck('slug')->all())->toEqualCanonicalizing([
            AiBotRoster::CHIEF_SLUG,
            ...AiBotRoster::SPECIALIST_SLUGS,
        ]);
});

it('keeps every seeded bot approval-gated, workspace-only, and free of coordinator write tools', function () {
    $workspace = Workspace::factory()->create();
    $superAdmin = User::factory()->superAdmin()->create();

    $bots = AiBotRoster::seedForWorkspace($workspace, $superAdmin);

    foreach ($bots as $bot) {
        expect($bot->policy['writes']['mode'])->toBe('approval_required')
            ->and($bot->policy['data_access']['workspace_only'])->toBeTrue();
    }

    $chief = $bots[AiBotRoster::CHIEF_SLUG];

    expect($chief->allowed_tools)->not->toContain('remember_bot_memory')
        ->and(array_intersect($chief->allowed_tools, ToolRegistry::writeKeys()))->toBe([]);

    foreach (AiBotRoster::SPECIALIST_SLUGS as $slug) {
        expect($bots[$slug]->allowed_tools)->toContain('remember_bot_memory');
    }
});

it('seeds the roster idempotently without overwriting operator edits', function () {
    $workspace = Workspace::factory()->create();
    $superAdmin = User::factory()->superAdmin()->create();

    $first = AiBotRoster::seedForWorkspace($workspace, $superAdmin);
    $first[AiBotRoster::CHIEF_SLUG]->update(['name' => 'Renamed Chief']);

    $second = AiBotRoster::seedForWorkspace($workspace, $superAdmin);

    expect($second[AiBotRoster::CHIEF_SLUG]->name)->toBe('Renamed Chief')
        ->and(AiBot::query()->withoutGlobalScope('workspace')->where('workspace_id', $workspace->id)->count())->toBe(9);

    $routines = AiBotRoutine::query()
        ->withoutGlobalScope('workspace')
        ->where('workspace_id', $workspace->id)
        ->where('bot_id', $second['engagement-manager']->id)
        ->get();

    expect($routines)->toHaveCount(1)
        ->and($routines->first()->name)->toBe('Monday engagement pulse')
        ->and($routines->first()->cron)->toBe('0 8 * * 1');
});
