<?php

/**
 * Dynamic favicon — serves the uploaded school logo as a square PNG.
 *
 * The logo lives under the `school_logo_path` setting (AiSettings -> School
 * Branding). When none is set the route falls back to the bundled static
 * icons, so the browser tab stays branded even before a logo is uploaded.
 */

use App\Models\Setting;
use App\Support\FaviconUrl;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;

// 40x20 red rectangle with transparency — a horizontal logo shape, so the
// contain-fit square conversion has something to center.
$testLogoPng = base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAACgAAAAUCAYAAAD/Rn+7AAAACXBIWXMAAA7EAAAOxAGVKw4bAAAANklEQVRIie3OMQEAMAjAMIYY/AualyGBo8c4GgU5t+rFYvk7MDFIGaQMUgYpg5RByiBlkDJINRa+Ak9S2Z+zAAAAAElFTkSuQmCC',
    true
);

beforeEach(function () {
    Setting::flushAllCaches();
    Storage::fake('public');
});

it('redirects to the bundled favicon when no logo is uploaded', function () {
    get(route('favicon'))->assertRedirect('/favicon.ico');
});

it('redirects to the bundled touch icon when no logo is uploaded and a large size is requested', function () {
    get(route('favicon', ['size' => 180]))->assertRedirect('/apple-touch-icon.png');
});

it('serves the uploaded logo as a square png favicon', function () use ($testLogoPng) {
    Storage::disk('public')->put('branding/logo.png', $testLogoPng);
    Setting::set('school_logo_path', 'branding/logo.png');

    $response = get(route('favicon'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'max-age=3600, public')
        ->assertHeader('ETag');

    $info = getimagesizefromstring($response->getContent());

    expect($info)->not->toBeFalse()
        ->and($info[0])->toBe(64)
        ->and($info[1])->toBe(64);
});

it('serves the requested size for the apple touch icon', function () use ($testLogoPng) {
    Storage::disk('public')->put('branding/logo.png', $testLogoPng);
    Setting::set('school_logo_path', 'branding/logo.png');

    $response = get(route('favicon', ['size' => 180]));

    $response->assertOk();

    $info = getimagesizefromstring($response->getContent());

    expect($info)->not->toBeFalse()
        ->and($info[0])->toBe(180)
        ->and($info[1])->toBe(180);
});

it('falls back to the bundled favicon when the stored logo file is missing', function () {
    Setting::set('school_logo_path', 'branding/logo.png');

    get(route('favicon'))->assertRedirect('/favicon.ico');
});

it('serves the uploaded logo and falls back once it is removed', function () use ($testLogoPng) {
    Storage::disk('public')->put('branding/logo.png', $testLogoPng);
    Setting::set('school_logo_path', 'branding/logo.png');

    expect(get(route('favicon'))->getContent())->not->toBeEmpty();

    Setting::set('school_logo_path', null);

    get(route('favicon'))->assertRedirect('/favicon.ico');
});

it('reports no logo and a constant version before one is uploaded', function () {
    expect(FaviconUrl::hasLogo())->toBeFalse()
        ->and(FaviconUrl::version())->toBe('0');

    $url = FaviconUrl::url();
    $touch = FaviconUrl::url(180);

    expect($url)->toContain('/favicon.png')->toContain('v=0')
        ->and($touch)->toContain('size=180')->toContain('v=0');
});

it('cache-busts the favicon URL against the uploaded logo path', function () use ($testLogoPng) {
    Storage::disk('public')->put('branding/logo.png', $testLogoPng);
    Setting::set('school_logo_path', 'branding/logo.png');

    expect(FaviconUrl::hasLogo())->toBeTrue();

    $firstVersion = FaviconUrl::version();
    $firstUrl = FaviconUrl::url();

    expect($firstVersion)->toHaveLength(8)
        ->and($firstUrl)->toContain('v=' . $firstVersion);

    // Re-uploading under a new path changes the version → browsers re-fetch.
    Storage::disk('public')->put('branding/new-logo.png', $testLogoPng);
    Setting::set('school_logo_path', 'branding/new-logo.png');

    expect(FaviconUrl::version())->not->toBe($firstVersion)
        ->and(FaviconUrl::url())->not->toBe($firstUrl);
});
