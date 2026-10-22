<?php

namespace App\Documents\Support;

use App\Documents\Enums\DateFormat;
use Brick\Money\Money;
use DateTimeInterface;

/**
 * Formatting for values shown on documents.
 */
final class Format
{
    private const LOCALE = 'en';

    public static function money(Money $money): string
    {
        return $money->formatToLocale(self::LOCALE);
    }

    public static function date(?DateTimeInterface $date, DateFormat $format): ?string
    {
        return $date?->format($format->value);
    }

    /**
     * Format a decimal string for display, e.g. "1250.5" becomes "1,250.5".
     */
    public static function decimal(string $value): string
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, null);

        return number_format((int) $whole).($fraction !== null ? ".{$fraction}" : '');
    }
}
