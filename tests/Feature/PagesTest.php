<?php

use App\Documents\DocumentCalculator;

it('renders the editor with landing copy, meta and Open Graph tags', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<title>GenDoc – Free Quote, Invoice &amp; Credit Note Generator</title>', false)
        ->assertSee('<h1 id="intro-heading"', false)
        ->assertSee('<meta name="description"', false)
        ->assertSee('<meta property="og:title"', false)
        ->assertSee('<meta property="og:image" content="'.asset('og-image.png').'">', false)
        ->assertSee('<link rel="icon" href="'.asset('favicon.svg').'" type="image/svg+xml">', false)
        ->assertSee(route('privacy'));
});

it('serves the privacy page', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertSee('<title>Privacy – GenDoc</title>', false)
        ->assertSee('Your documents stay in your browser')
        ->assertSee('Nothing is logged or stored')
        ->assertSee('cookieless and anonymous')
        ->assertSee('essential session cookie');
});

it('shows a friendly 404 page', function () {
    $this->get('/no-such-page')
        ->assertNotFound()
        ->assertSee('Page not found')
        ->assertSee(route('home'));
});

describe('tip button', function () {
    it('is hidden when no URL is set', function () {
        config(['services.tips.url' => null]);

        $this->get(route('home'))->assertDontSee('Support this free tool');
    });

    it('links to the tip URL in a new tab', function () {
        config(['services.tips.url' => 'https://ko-fi.com/example']);

        $this->get(route('home'))
            ->assertSee('href="https://ko-fi.com/example"', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener"', false)
            ->assertSee('Support this free tool');
    });

    it('never appears in the document itself', function () {
        config(['services.tips.url' => 'https://ko-fi.com/example']);

        $document = document();
        $html = view('documents.render', ['document' => $document, 'totals' => app(DocumentCalculator::class)->calculate($document)])->render();

        expect($html)->not->toContain('Support this free tool');
    });
});

describe('analytics', function () {
    it('renders nothing unless both settings are present', function (?string $url, ?string $id) {
        config(['services.umami.script_url' => $url, 'services.umami.website_id' => $id]);

        $this->get(route('home'))->assertDontSee('data-website-id', false);
    })->with([
        [null, null],
        ['https://analytics.owright.dev/script.js', null],
        [null, 'abc-123'],
    ]);

    it('includes the tracking script and allows its origin in the CSP', function () {
        config(['services.umami.script_url' => 'https://analytics.owright.dev/script.js', 'services.umami.website_id' => 'abc-123']);

        $response = $this->get(route('home'))
            ->assertSee('<script defer src="https://analytics.owright.dev/script.js" data-website-id="abc-123"', false);

        expect($response->headers->get('Content-Security-Policy'))
            ->toMatch("#script-src [^;]*https://analytics\.owright\.dev#")
            ->toMatch("#connect-src [^;]*https://analytics\.owright\.dev#");
    });
});
