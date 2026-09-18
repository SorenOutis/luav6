<?php

it('renders the welcome page', function () {
    $this->get(route('home'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('Welcome'));
});

it('ships the early low-end detector in the document head', function () {
    $html = $this->get(route('home'))->assertSuccessful()->getContent();

    expect($html)
        ->toContain("setAttribute('data-low-end'")
        ->toContain('(pointer: coarse)')
        ->toContain('deviceMemory');
});
