<?php

namespace App\Documents\Validation\Rules;

use Brick\Money\Currency;
use Brick\Money\CurrencyType;
use Brick\Money\Exception\UnknownCurrencyException;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class Iso4217Currency implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^[A-Z]{3}$/', $value)) {
            $fail('The :attribute must be a three-letter ISO 4217 currency code.');

            return;
        }

        try {
            $current = Currency::of($value)->getCurrencyType() === CurrencyType::IsoCurrent;
        } catch (UnknownCurrencyException) {
            $current = false;
        }

        if (! $current) {
            $fail('The :attribute is not a current ISO 4217 currency code.');
        }
    }
}
