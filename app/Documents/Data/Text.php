<?php

namespace App\Documents\Data;

final class Text
{
    /**
     * Trim a user-entered value, turning blank strings into null so empty fields can be hidden.
     */
    public static function orNull(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
