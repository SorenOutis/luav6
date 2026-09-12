<?php

test('guests can visit the privacy policy page', function () {
    $this->withoutVite();
    $this->get(route('privacy'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Privacy')
            ->has('canRegister')
        );
});

test('guests can visit the terms page', function () {
    $this->withoutVite();
    $this->get(route('terms'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Terms')
            ->has('canRegister')
        );
});

test('guests can visit the cookie policy page', function () {
    $this->withoutVite();
    $this->get(route('cookies'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Cookies')
            ->has('canRegister')
        );
});

test('sitemap lists the legal pages', function () {
    $content = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($content)
        ->toContain('/privacy')
        ->toContain('/terms')
        ->toContain('/cookies');
});

test('welcome footer links to the legal pages and cookie settings', function () {
    $source = file_get_contents(resource_path('js/components/welcome/WelcomeFooter.vue'));

    expect($source)
        ->toContain('/privacy')
        ->toContain('/terms')
        ->toContain('/cookies')
        ->toContain('Cookie settings')
        ->toContain('CookieConsentBanner');
});

test('cookie banner is mounted in app and auth layouts', function () {
    expect(file_get_contents(resource_path('js/layouts/app/AppSidebarLayout.vue')))
        ->toContain('CookieConsentBanner');
    expect(file_get_contents(resource_path('js/layouts/auth/AuthCardLayout.vue')))
        ->toContain('CookieConsentBanner');
});

test('cookie banner records a stored choice and links the cookie policy', function () {
    $source = file_get_contents(resource_path('js/components/CookieConsentBanner.vue'));

    expect($source)
        ->toContain('lsi-cookie-consent')
        ->toContain('/cookies')
        ->toContain('Essential only')
        ->toContain('Accept all');
});

test('register form asks for privacy consent and discloses data collection', function () {
    $source = file_get_contents(resource_path('js/pages/auth/Register.vue'));

    expect($source)
        ->toContain('/privacy')
        ->toContain('/terms')
        ->toContain('Privacy Policy')
        ->toContain('We collect your name');
});

test('support form links the privacy policy', function () {
    $source = file_get_contents(resource_path('js/pages/Support.vue'));

    expect($source)->toContain('/privacy');
});

test('anonymous feed makes no encryption or absolute anonymity claims', function () {
    $source = file_get_contents(resource_path('js/pages/Ngl.vue'));

    expect($source)
        ->not->toContain('encryption')
        ->not->toContain('Encrypting')
        ->not->toContain('100% Anonymous')
        ->not->toContain('elite encryption')
        ->toContain('Your name is never shown');
});

test('legal pages and banner use plain punctuation only', function () {
    $files = [
        resource_path('js/pages/Privacy.vue'),
        resource_path('js/pages/Terms.vue'),
        resource_path('js/pages/Cookies.vue'),
        resource_path('js/components/CookieConsentBanner.vue'),
    ];

    foreach ($files as $file) {
        $source = file_get_contents($file);

        expect($source)->not->toContain("\u{2014}", "Em dash found in {$file}")
            ->and($source)->not->toContain("\u{2013}", "En dash found in {$file}")
            ->and($source)->not->toContain("\u{2026}", "Ellipsis character found in {$file}");
    }
});
