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

it('reduces resolved urls back to the stored path', function () {
    $host = parse_url(config('app.url'), PHP_URL_HOST);

    expect(PublicFileUrl::storedPath("https://{$host}/avatars/avatar-03.svg"))
        ->toBe('avatars/avatar-03.svg');

    expect(PublicFileUrl::storedPath("https://{$host}/storage/avatars/one.png"))
        ->toBe('avatars/one.png');

    expect(PublicFileUrl::storedPath("https://{$host}/storage/covers/three.jpg?v=2"))
        ->toBe('covers/three.jpg');

    expect(PublicFileUrl::storedPath('//'.$host.'/storage/avatars/four.png'))
        ->toBe('avatars/four.png');

    expect(PublicFileUrl::storedPath("https://{$host}/"))->toBeNull();
});

it('reduces urls from the configured public disk back to the stored path', function () {
    config(['filesystems.disks.public.url' => 'https://pub-abc.r2.dev']);

    expect(PublicFileUrl::storedPath('https://pub-abc.r2.dev/custom-avatars/two.webp'))
        ->toBe('custom-avatars/two.webp');
});

it('leaves plain paths alone when reducing them to stored paths', function () {
    expect(PublicFileUrl::storedPath('avatars/one.png'))->toBe('avatars/one.png');
    expect(PublicFileUrl::storedPath('/avatars/one.png'))->toBe('avatars/one.png');
    expect(PublicFileUrl::storedPath('/storage/avatars/one.png'))->toBe('avatars/one.png');
    expect(PublicFileUrl::storedPath('  avatars/one.png  '))->toBe('avatars/one.png');
});

it('never mangles values it cannot recognise', function () {
    expect(PublicFileUrl::storedPath(null))->toBeNull();
    expect(PublicFileUrl::storedPath(''))->toBeNull();
    expect(PublicFileUrl::storedPath('   '))->toBeNull();

    // Provider avatars and foreign buckets live in the same column: a URL on a
    // host that is not ours carries no disk path to reduce, so it is returned
    // untouched rather than truncated into something unrecognisable.
    expect(PublicFileUrl::storedPath('https://lh3.googleusercontent.com/a/abc'))
        ->toBe('https://lh3.googleusercontent.com/a/abc');

    expect(PublicFileUrl::storedPath('https://example.com/avatars/one.png'))
        ->toBe('https://example.com/avatars/one.png');

    expect(PublicFileUrl::storedPath('https://example.com/'))
        ->toBe('https://example.com/');
});

it('round trips resolved paths through storedPath', function () {
    foreach (['avatars/avatar-01.svg', 'avatars/one.png', 'covers/two.jpg'] as $path) {
        expect(PublicFileUrl::storedPath(PublicFileUrl::resolve($path)))->toBe($path);
    }
});
