<?php

use App\Documents\Data\DocumentStyle;
use App\Documents\Enums\Font;
use App\Documents\Support\AccentColour;

describe('AccentColour', function () {
    it('picks white or near-black text for an accent background', function (string $accent, string $expected) {
        expect(AccentColour::onAccent($accent))->toBe($expected);
    })->with([
        'dark blue' => ['#2563eb', '#ffffff'],
        'slate' => ['#0f172a', '#ffffff'],
        'yellow' => ['#fde047', '#111827'],
        'white' => ['#ffffff', '#111827'],
    ]);

    it('keeps accents that already read well on white', function () {
        expect(AccentColour::asText('#2563eb'))->toBe('#2563eb');
    });

    it('darkens light accents until they reach 4.5:1 on white', function (string $accent) {
        $text = AccentColour::asText($accent);

        expect($text)->not->toBe($accent)
            ->and(AccentColour::contrast($text, '#ffffff'))->toBeGreaterThanOrEqual(4.5);
    })->with(['#fde047', '#ffffff', '#22d3ee']);

    it('measures contrast ratios', function () {
        expect(AccentColour::contrast('#000000', '#ffffff'))->toBe(21.0)
            ->and(AccentColour::contrast('#777777', '#777777'))->toBe(1.0);
    });
});

describe('Font', function () {
    it('names the family declared by its @fontsource stylesheets', function (Font $font) {
        $declared = collect($font->stylesheets())
            ->map(fn (string $path) => file_get_contents(dirname(__DIR__, 3)."/node_modules/{$path}"))
            ->implode('');

        expect($declared)->toContain("font-family: '{$font->family()}';");
    })->with(Font::cases());

    it('is imported by the app stylesheet', function (Font $font) {
        $css = file_get_contents(dirname(__DIR__, 3).'/resources/css/fonts.css');

        foreach ($font->stylesheets() as $path) {
            expect($css)->toContain("@import '{$path}';");
        }
    })->with(Font::cases());

    it('falls back to a generic family of the same kind', function () {
        expect(Font::Merriweather->cssFamily())->toBe("'Merriweather Variable', Georgia, serif")
            ->and(Font::Lato->cssFamily())->toBe("'Lato', Arial, sans-serif");
    });
});

it('exposes the style as CSS custom properties', function () {
    $style = new DocumentStyle(accentColor: '#fde047', font: Font::Roboto);
    $accentText = AccentColour::asText('#fde047');

    expect($style->cssVariables())->toBe(
        "--accent: #fde047; --accent-text: {$accentText}; --accent-ink: #111827; --doc-font: 'Roboto Variable', Arial, sans-serif;"
    );
});
