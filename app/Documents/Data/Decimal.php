<?php

namespace App\Documents\Data;

use Brick\Math\BigDecimal;

/**
 * Helpers for the decimal strings used for quantities and percentages.
 */
final class Decimal
{
    /**
     * Normalise a decimal string, e.g. "20.000" becomes "20" and "02.50" becomes "2.5".
     */
    public static function normalise(string $value): string
    {
        return (string) BigDecimal::of($value)->strippedOfTrailingZeros();
    }
}
