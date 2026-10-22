<?php

namespace App\Documents\Support;

/**
 * Derives readable colours from the user's accent colour, so any choice keeps text legible.
 *
 * Contrast follows WCAG 2: text needs a ratio of at least 4.5:1 against its background.
 */
final class AccentColour
{
    private const WHITE = '#ffffff';

    private const INK = '#111827';

    private const MIN_TEXT_CONTRAST = 4.5;

    /**
     * Text colour to use on an accent-filled background: white, or near-black for light accents.
     */
    public static function onAccent(string $hex): string
    {
        return self::contrast($hex, self::WHITE) >= self::contrast($hex, self::INK) ? self::WHITE : self::INK;
    }

    /**
     * The accent for use as text on white, darkened just enough to reach 4.5:1 if it is too light.
     */
    public static function asText(string $hex): string
    {
        [$r, $g, $b] = self::channels($hex);

        for ($step = 0; $step <= 20; $step++) {
            $factor = 1 - $step / 20;
            $candidate = self::hex((int) round($r * $factor), (int) round($g * $factor), (int) round($b * $factor));

            if (self::contrast($candidate, self::WHITE) >= self::MIN_TEXT_CONTRAST) {
                return $candidate;
            }
        }

        return self::INK;
    }

    public static function contrast(string $a, string $b): float
    {
        $lighter = max(self::luminance($a), self::luminance($b));
        $darker = min(self::luminance($a), self::luminance($b));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private static function luminance(string $hex): float
    {
        $linear = array_map(function (int $channel): float {
            $value = $channel / 255;

            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, self::channels($hex));

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }

    /**
     * @return array{int, int, int}
     */
    private static function channels(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }

    private static function hex(int $r, int $g, int $b): string
    {
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
