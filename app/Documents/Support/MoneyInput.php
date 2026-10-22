<?php

namespace App\Documents\Support;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Brick\Money\Currency;

/**
 * Converts between the amounts people type (e.g. "1,250.50") and integer minor units.
 */
final class MoneyInput
{
    /**
     * Parse typed input into minor units. Returns null if it isn't a non-negative amount, or (when
     * strict) if it has more decimal places than the currency allows.
     */
    public static function toMinor(string $input, Currency $currency, bool $strict = true): ?int
    {
        $normalised = str_replace([',', ' '], '', trim($input));

        if ($normalised === '') {
            return 0;
        }

        if (! preg_match('/^\d+(\.\d+)?$/', $normalised)) {
            return null;
        }

        $digits = $currency->getDefaultFractionDigits();

        try {
            $amount = BigDecimal::of($normalised);
            $scaled = $strict
                ? $amount->toScale($digits)
                : $amount->toScale($digits, RoundingMode::HalfUp);

            return $scaled->withPointMovedRight($digits)->toInt();
        } catch (MathException) {
            return null;
        }
    }

    /**
     * Format minor units for an input field, e.g. 1250 GBP becomes "12.50" and 1250 JPY "1250".
     */
    public static function fromMinor(int $minor, Currency $currency): string
    {
        return (string) BigDecimal::ofUnscaledValue($minor, $currency->getDefaultFractionDigits());
    }
}
