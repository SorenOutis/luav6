<?php

use App\Support\Seo;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Server-rendered social metadata
|--------------------------------------------------------------------------
|
| Link-preview crawlers (Facebook, X, Slack, WhatsApp, LinkedIn, iMessage)
| never execute JavaScript, so the og:/twitter: tags the client-side
| <SeoHead> component emits are invisible to them. App\Support\Seo mirrors
| that copy into the first HTML response via resources/views/app.blade.php.
|
| These tests pin the three ways that mirror can silently drift:
|
|   1. the PHP registry vs. the copy each Vue page declares;
|   2. the tags actually present in the HTML crawlers fetch;
|   3. pages outside the registry staying out of link previews entirely.
|
*/

it('keeps the Seo registry in sync with the copy each page declares', function () {
    $pages = Seo::pages();

    expect($pages)->not->toBeEmpty();

    foreach ($pages as $component => $entry) {
        $path = resource_path("js/pages/{$component}.vue");

        $this->assertFileExists(
            $path,
            "App\\Support\\Seo::pages() lists [{$component}], but {$path} does not exist.",
        );

        $source = (string) file_get_contents($path);

        $this->assertSame(
            1,
            preg_match('/<SeoHead\b[^>]*(?:\/>|>.*?<\/SeoHead>)/s', $source, $seoBlock),
            "{$component}.vue no longer renders a <SeoHead> block.",
        );

        foreach (['title', 'description', 'type'] as $attribute) {
            $this->assertSame(
                1,
                preg_match('/\b'.$attribute.'="([^"]*)"/', $seoBlock[0], $match),
                "<SeoHead> in {$component}.vue has no static {$attribute} attribute.",
            );

            $this->assertSame(
                $entry[$attribute],
                $match[1],
                "The <SeoHead {$attribute}> in {$component}.vue drifted from ".
                    "App\\Support\\Seo::pages()['{$component}'] — update both together.",
            );
        }

        // The browser-tab title is set client-side via Inertia's <Head
        // title>; app.blade.php must render the same string server-side so
        // crawlers and hydrated browsers agree on the document title.
        $this->assertSame(
            1,
            preg_match('/<Head\b[^>]*\btitle="([^"]*)"/', $source, $headTitle),
            "{$component}.vue no longer declares <Head title=\"...\">.",
        );

        $this->assertSame(
            $entry['title'],
            $headTitle[1],
            "<Head title> in {$component}.vue drifted from the Seo registry title.",
        );
    }
});

it('server-renders the social card in the HTML crawlers fetch', function () {
    $entry = Seo::pages()['Welcome'];
    $origin = rtrim((string) config('seo.site_url'), '/');
    $image = $origin.'/'.ltrim((string) config('seo.og_image'), '/');

    $response = $this->get('/');

    $response->assertOk();

    $html = $response->getContent();

    expect($html)
        ->toContain('<title inertia>'.e($entry['title']).' - '.e((string) config('app.name')).'</title>')
        ->toContain('<meta name="description" content="'.e($entry['description']).'">')
        ->toContain('<link rel="canonical" href="'.$origin.'/">')
        ->toContain('<meta name="robots" content="index, follow">')
        ->toContain('<meta property="og:type" content="'.$entry['type'].'">')
        ->toContain('<meta property="og:site_name" content="'.e((string) config('seo.site_name')).'">')
        ->toContain('<meta property="og:title" content="'.e($entry['title']).'">')
        ->toContain('<meta property="og:description" content="'.e($entry['description']).'">')
        ->toContain('<meta property="og:url" content="'.$origin.'/">')
        ->toContain('<meta property="og:image" content="'.$image.'">')
        ->toContain('<meta property="og:image:width" content="1200">')
        ->toContain('<meta property="og:image:height" content="630">')
        ->toContain('<meta property="og:locale" content="'.e((string) config('seo.locale')).'">')
        ->toContain('<meta name="twitter:card" content="summary_large_image">')
        ->toContain('<meta name="twitter:title" content="'.e($entry['title']).'">')
        ->toContain('<meta name="twitter:description" content="'.e($entry['description']).'">')
        ->toContain('<meta name="twitter:image" content="'.$image.'">');
});

it('derives canonical, og:url, and the social card per route', function () {
    $entry = Seo::pages()['Privacy'];
    $origin = rtrim((string) config('seo.site_url'), '/');

    $response = $this->get('/privacy');

    $response->assertOk();

    $html = $response->getContent();

    expect($html)
        ->toContain('<title inertia>'.e($entry['title']).' - '.e((string) config('app.name')).'</title>')
        ->toContain('<link rel="canonical" href="'.$origin.'/privacy">')
        ->toContain('<meta property="og:url" content="'.$origin.'/privacy">')
        ->toContain('<meta property="og:title" content="'.e($entry['title']).'">')
        ->toContain('<meta property="og:type" content="article">');
});

it('keeps pages outside the public registry out of link previews', function () {
    expect(Seo::forPage(['component' => 'Dashboard', 'props' => []], request()))->toBeNull();

    Route::get('/__seo-private-probe', fn () => inertia('Dashboard', []))->middleware('web');

    $response = $this->get('/__seo-private-probe');

    $response->assertOk();

    expect($response->getContent())
        ->not->toContain('property="og:title"')
        ->not->toContain('rel="canonical"')
        ->toContain('<title inertia>'.e((string) config('app.name')).'</title>');
});
