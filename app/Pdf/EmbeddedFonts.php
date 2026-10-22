<?php

namespace App\Pdf;

use App\Documents\Enums\Font;
use RuntimeException;

/**
 * Builds @font-face rules for one document font with the font files inlined as base64, so the
 * PDF renderer never has to fetch anything. Reads the same @fontsource stylesheets the editor uses.
 */
final class EmbeddedFonts
{
    /** @var array<string, string> */
    private array $cache = [];

    public function __construct(private readonly string $packagesPath) {}

    public function css(Font $font): string
    {
        return $this->cache[$font->value] ??= implode("\n", array_map($this->inline(...), $font->stylesheets()));
    }

    private function inline(string $stylesheet): string
    {
        $path = "{$this->packagesPath}/{$stylesheet}";
        // Chromium always picks woff2, so drop the older woff fallbacks some packages also list.
        $css = (string) preg_replace('#,\s*url\(\./files/[\w.-]+\.woff\) format\([\'"]woff[\'"]\)#', '', $this->read($path));

        // @fontsource stylesheets reference their files as url(./files/<name>.woff2).
        $inlined = preg_replace_callback(
            '#url\((["\']?)\./files/([\w.-]+\.woff2)\1\)#',
            fn (array $match): string => 'url(data:font/woff2;base64,'.base64_encode($this->read(dirname($path)."/files/{$match[2]}")).')',
            $css,
        );

        if ($inlined === null || str_contains($inlined, 'url(./')) {
            throw new RuntimeException("Could not inline every font file in {$stylesheet}.");
        }

        return $inlined;
    }

    private function read(string $path): string
    {
        $contents = is_file($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            throw new RuntimeException("Font file not found: {$path}");
        }

        return $contents;
    }
}
