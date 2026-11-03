<?php

use App\Filament\Resources\PlatformAiBots\Pages\CreatePlatformAiBot;
use App\Filament\Resources\PlatformAiBots\Pages\EditPlatformAiBot;
use App\Filament\Resources\PlatformAiBots\Pages\ListPlatformAiBots;
use App\Models\AiBot;
use App\Models\User;
use App\Models\Workspace;
use Livewire\Livewire;

it('lets super admins list and edit bots from every workspace', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $firstWorkspace = Workspace::factory()->create();
    $secondWorkspace = Workspace::factory()->create();

    $first = AiBot::factory()->create(['workspace_id' => $firstWorkspace->id]);
    $second = AiBot::factory()->create(['workspace_id' => $secondWorkspace->id]);

    $this->actingAs($superAdmin);

    Livewire::test(ListPlatformAiBots::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$first, $second]);

    Livewire::test(EditPlatformAiBot::class, ['record' => $first->id])
        ->fillForm([
            'policy.identity.role' => 'Updated Role',
            'policy.identity.mission' => 'Updated mission.',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($first->fresh()->policy['identity']['role'])->toBe('Updated Role')
        ->and($first->fresh()->policy['identity']['mission'])->toBe('Updated mission.');
});

it('lets super admins create a bot with approval-gated tools', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $workspace = Workspace::factory()->create();

    $this->actingAs($superAdmin);

    Livewire::test(CreatePlatformAiBot::class)
        ->fillForm([
            'workspace_id' => $workspace->id,
            'kind' => AiBot::KIND_SPECIALIST,
            'name' => 'New Bot',
            'slug' => 'new-bot',
            'policy.identity.role' => 'New Bot',
            'policy.identity.mission' => 'Handle new work.',
            'allowed_tools' => ['students', 'create_exam'],
            'max_steps' => 8,
            'memory_scope' => AiBot::MEMORY_SESSION,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $bot = AiBot::query()
        ->withoutGlobalScope('workspace')
        ->where('slug', 'new-bot')
        ->sole();

    expect($bot->workspace_id)->toBe($workspace->id)
        ->and($bot->kind)->toBe(AiBot::KIND_SPECIALIST)
        ->and($bot->allowed_tools)->toContain('students', 'create_exam')
        ->and($bot->policy['writes']['mode'])->toBe('approval_required')
        ->and($bot->policy['data_access']['workspace_only'])->toBeTrue();
});

it('forbids non-super-admins from the bot control center', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/platform-ai-bots')
        ->assertForbidden();
});
