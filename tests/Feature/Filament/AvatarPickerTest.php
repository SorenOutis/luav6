<?php

use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Filament\Models\Contracts\HasAvatar;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('exposes the user avatar to Filament panels', function () {
    $reflection = new ReflectionClass(User::class);

    expect($reflection->implementsInterface(HasAvatar::class))->toBeTrue()
        ->and((new User)->getFilamentAvatarUrl())->toBeNull();
});

it('uses the avatar picker in the administration user form', function () {
    $source = file_get_contents(base_path(
        'app/Filament/Resources/Users/Schemas/UserForm.php'
    ));

    expect($source)
        ->toContain('use MatondoJK\\FilamentAvatarPicker\\Components\\AvatarPicker;')
        ->toContain("AvatarPicker::make('avatar')")
        ->not->toContain("FileUpload::make('avatar')");
});

it('ships a curated avatar gallery for the picker', function () {
    $avatars = glob(storage_path('app/public/avatars/avatar-*.svg')) ?: [];

    expect($avatars)->toHaveCount(12);
});

it('hydrates the admin avatar field with the stored path instead of the accessor url', function () {
    $student = User::factory()->create(['avatar' => 'avatars/avatar-03.svg']);

    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(EditUser::class, ['record' => $student->getRouteKey()])
        ->assertFormSet(['avatar' => 'avatars/avatar-03.svg']);
});

it('keeps the avatar when the users edit form is saved', function () {
    $student = User::factory()->create(['avatar' => 'avatars/avatar-03.svg']);

    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(EditUser::class, ['record' => $student->getRouteKey()])
        ->call('save')
        ->assertHasNoErrors();

    // Read the column, not the accessor: reading `avatar` would resolve the
    // stored path back into a URL and hide what was actually saved.
    expect($student->fresh()->getRawOriginal('avatar'))->toBe('avatars/avatar-03.svg');
});

it('keeps an uploaded avatar that exists on the public disk', function () {
    Storage::fake('public');
    Storage::disk('public')->put('avatars/uploaded.png', 'png');

    $student = User::factory()->create(['avatar' => 'avatars/uploaded.png']);

    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(EditUser::class, ['record' => $student->getRouteKey()])
        ->assertFormSet(['avatar' => 'avatars/uploaded.png']);
});

it('drops an uploaded avatar that no longer exists on the public disk', function () {
    Storage::fake('public');

    $student = User::factory()->create(['avatar' => 'avatars/deleted.png']);

    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(EditUser::class, ['record' => $student->getRouteKey()])
        ->assertFormSet(['avatar' => null]);
});

it('leaves an avatar that is a provider url alone', function () {
    $providerAvatar = 'https://lh3.googleusercontent.com/a/abcdef';

    $student = User::factory()->create(['avatar' => $providerAvatar]);

    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(EditUser::class, ['record' => $student->getRouteKey()])
        ->assertFormSet(['avatar' => $providerAvatar]);
});

it('previews the avatar through the application url rather than the public disk', function () {
    $student = User::factory()->create(['avatar' => 'avatars/avatar-04.svg']);

    $this->actingAs(User::factory()->superAdmin()->create());

    $form = Livewire::test(EditUser::class, ['record' => $student->getRouteKey()])
        ->instance()
        ->form;

    $avatar = $form->getFlatComponents(withActions: false, withHidden: true)['avatar'];

    $files = $avatar->getUploadedFiles();

    expect($files)->toHaveCount(1)
        ->and(array_values($files)[0]['url'])->toBe(url('/avatars/avatar-04.svg'));
});
