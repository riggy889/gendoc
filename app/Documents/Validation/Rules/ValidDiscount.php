<?php

namespace App\Documents\Validation\Rules;

use App\Documents\Enums\DiscountType;
use App\Documents\Validation\DocumentRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a { type, value } discount: a percentage from 0 to 100 with up to 2 decimal places,
 * or a fixed amount in minor units.
 */
final class ValidDiscount implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || array_diff(array_keys($value), ['type', 'value']) !== []) {
            $fail('The :attribute must contain only a type and a value.');

            return;
        }

        $type = DiscountType::tryFrom(is_string($value['type'] ?? null) ? $value['type'] : '');
        $amount = $value['value'] ?? null;

        $valid = match ($type) {
            DiscountType::Percent => (is_string($amount) || is_int($amount) || is_float($amount))
                && preg_match('/^\d{1,3}(\.\d{1,2})?$/', (string) $amount) === 1
                && (float) $amount <= 100,
            DiscountType::Fixed => is_int($amount) && $amount >= 0 && $amount <= DocumentRules::MAX_MINOR_AMOUNT,
            null => false,
        };

        if (! $valid) {
            $fail('The :attribute must be a percentage from 0 to 100 or a fixed amount that is not negative.');
        }
    }
}
