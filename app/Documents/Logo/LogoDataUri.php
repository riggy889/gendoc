<?php

namespace App\Documents\Logo;

use enshrined\svgSanitize\Sanitizer;

/**
 * Parses, checks and sanitises logo data URIs (base64-encoded PNG, JPEG, WebP or SVG).
 */
final class LogoDataUri
{
    public const MAX_BYTES = 2 * 1024 * 1024;

    public const SVG = 'image/svg+xml';

    /** @var list<string> */
    public const ALLOWED_TYPES = ['image/png', 'image/jpeg', 'image/webp', self::SVG];

    /**
     * Split a data URI into its media type and decoded bytes, or return null if it is not a
     * well-formed base64 data URI of an allowed type.
     *
     * @return array{type: string, bytes: string}|null
     */
    public static function parse(string $dataUri): ?array
    {
        if (! preg_match('#^data:([a-z0-9.+/-]+);base64,([A-Za-z0-9+/]*={0,2})$#', $dataUri, $matches)) {
            return null;
        }

        [, $type, $base64] = $matches;

        if (! in_array($type, self::ALLOWED_TYPES, true)) {
            return null;
        }

        $bytes = base64_decode($base64, true);

        if ($bytes === false || $bytes === '') {
            return null;
        }

        return ['type' => $type, 'bytes' => $bytes];
    }

    /**
     * Check that the decoded bytes really are the declared image type.
     */
    public static function contentMatchesType(string $type, string $bytes): bool
    {
        return match ($type) {
            'image/png' => str_starts_with($bytes, "\x89PNG\r\n\x1a\n"),
            'image/jpeg' => str_starts_with($bytes, "\xFF\xD8\xFF"),
            'image/webp' => str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP',
            self::SVG => self::sanitiseSvg($bytes) !== null,
            default => false,
        };
    }

    /**
     * Whether the SVG contains anything the sanitiser would remove (scripts, event handlers,
     * remote references and so on).
     */
    public static function svgHasUnsafeContent(string $svg): bool
    {
        $sanitizer = self::sanitizer();

        return $sanitizer->sanitize($svg) === false || $sanitizer->getXmlIssues() !== [];
    }

    /**
     * Return the data URI with any SVG content sanitised. Raster images are returned unchanged.
     * Unparseable input becomes null.
     */
    public static function sanitise(string $dataUri): ?string
    {
        $parsed = self::parse($dataUri);

        if ($parsed === null) {
            return null;
        }

        if ($parsed['type'] !== self::SVG) {
            return $dataUri;
        }

        $clean = self::sanitiseSvg($parsed['bytes']);

        return $clean === null ? null : 'data:'.self::SVG.';base64,'.base64_encode($clean);
    }

    private static function sanitiseSvg(string $svg): ?string
    {
        $clean = self::sanitizer()->sanitize($svg);

        return is_string($clean) && $clean !== '' ? $clean : null;
    }

    private static function sanitizer(): Sanitizer
    {
        $sanitizer = new Sanitizer;
        $sanitizer->removeRemoteReferences(true);

        return $sanitizer;
    }
}
