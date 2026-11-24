<?php

uses()->group('spa');

test('web routes serve the SPA shell', function (string $path) {
    $this->withoutVite();

    $this->get($path)
        ->assertOk()
        ->assertSee('<div id="app"></div>', escape: false);
})->with(['/', '/login', '/register', '/makers/some-maker']);

test('unknown api routes are not swallowed by the SPA', function () {
    $this->getJson('/api/does-not-exist')->assertNotFound();
});
