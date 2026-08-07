<?php

use App\Support\PublicFileUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('returns null for blank paths', function () {
    expect(PublicFileUrl::resolve(null))->toBeNull();
    expect(PublicFileUrl::resolve(''))->toBeNull();
    expect(PublicFileUrl::resolve('   '))->toBeNull();
});

it('returns absolute URLs unchanged instead of double-prefixing', function () {
    expect(PublicFileUrl::resolve('https://pub-abc.r2.dev/avatars/one.png'))
        ->toBe('https://pub-abc.r2.dev/avatars/one.png');

    expect(PublicFileUrl::resolve('http://example.com/branding/logo.png'))
        ->toBe('http://example.com/branding/logo.png');

    expect(PublicFileUrl::resolve('//cdn.example.com/x.png'))
        ->toBe('//cdn.example.com/x.png');
});

it('prefixes relative paths with the public disk url', function () {
    $url = PublicFileUrl::resolve('avatars/one.png');

    expect($url)
        ->toBeString()
        ->toContain('/storage/avatars/one.png');
});
