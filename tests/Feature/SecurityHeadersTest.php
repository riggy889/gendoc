<?php

it('sends security headers on every page', function (string $uri) {
    $response = $this->get($uri);

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($response->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'")
        ->toContain("object-src 'none'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("'unsafe-eval'");
})->with(['/', '/privacy', '/no-such-page']);

it('allows inline scripts and styles only with the page nonce', function () {
    $response = $this->get('/');
    $csp = $response->headers->get('Content-Security-Policy');

    preg_match("/script-src [^;]*'nonce-([^']+)'/", $csp, $match);

    expect($match[1] ?? null)->not->toBeNull()
        ->and($csp)->not->toMatch("/script-src [^;]*'unsafe-inline'/")
        ->and($response->getContent())->toContain('window.livewireScriptConfig')
        ->toMatch('/<script nonce="'.preg_quote($match[1], '/').'"[^>]*>window\.livewireScriptConfig/');
});

it('uses a fresh nonce for each request', function () {
    $nonce = fn () => preg_replace("/.*'nonce-([^']+)'.*/s", '$1', $this->get('/')->headers->get('Content-Security-Policy'));

    expect($nonce())->not->toBe($nonce());
});

it('sets only the session cookie', function () {
    $cookies = collect($this->get('/')->headers->getCookies())->map->getName();

    expect($cookies->all())->toBe([config('session.cookie')]);
});
