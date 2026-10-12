<?php

use App\Filament\Resources\Admins\Pages\EditAdmin;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('updates an admin without changing password when password is left blank', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $admin = User::factory()->admin()->create([
        'password' => Hash::make('original-password'),
    ]);
    $workspace = Workspace::factory()->create();
    $admin->forceFill(['current_workspace_id' => $workspace->id])->save();

    $this->actingAs($superAdmin);

    Livewire::test(EditAdmin::class, ['record' => $admin->id])
        ->fillForm([
            'first_name' => 'UpdatedFirst',
            'last_name' => 'UpdatedLast',
            'email' => 'updated.admin@example.com',
            'password' => null,
            'password_confirmation' => null,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $admin->refresh();
    expect($admin->first_name)->toBe('UpdatedFirst')
        ->and($admin->last_name)->toBe('UpdatedLast')
        ->and($admin->email)->toBe('updated.admin@example.com')
        ->and(Hash::check('original-password', $admin->password))->toBeTrue();
});

it('updates an admin password when a new password is provided', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $admin = User::factory()->admin()->create([
        'password' => Hash::make('original-password'),
    ]);
    $workspace = Workspace::factory()->create();
    $admin->forceFill(['current_workspace_id' => $workspace->id])->save();

    $this->actingAs($superAdmin);

    Livewire::test(EditAdmin::class, ['record' => $admin->id])
        ->fillForm([
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $admin->refresh();
    expect(Hash::check('new-secret-password', $admin->password))->toBeTrue();
});
