<?php

use App\Documents\Enums\Font;
use App\Pdf\EmbeddedFonts;

it('inlines every file of each font as base64', function (Font $font) {
    $css = (new EmbeddedFonts(dirname(__DIR__, 3).'/node_modules'))->css($font);

    expect($css)
        ->toContain("font-family: '{$font->family()}'")
        ->toContain('src: url(data:font/woff2;base64,')
        ->not->toContain('url(./');
})->with(Font::cases());

it('fails loudly when the font files are missing', function () {
    (new EmbeddedFonts('/nonexistent'))->css(Font::Inter);
})->throws(RuntimeException::class, 'Font file not found');
