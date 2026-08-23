<?php

use App\Models\User;
use Filament\Models\Contracts\HasAvatar;

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
