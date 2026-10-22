<?php

namespace App\Documents\Validation\Rules;

use App\Documents\Logo\LogoDataUri;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class LogoImage implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $parsed = is_string($value) ? LogoDataUri::parse($value) : null;

        if ($parsed === null) {
            $fail('The logo must be a PNG, JPEG, WebP or SVG image.');

            return;
        }

        if (strlen($parsed['bytes']) > LogoDataUri::MAX_BYTES) {
            $fail('The logo must be 2 MB or smaller.');

            return;
        }

        if (! LogoDataUri::contentMatchesType($parsed['type'], $parsed['bytes'])) {
            $fail('The logo file is not a valid image of the type it claims to be.');

            return;
        }

        if ($parsed['type'] === LogoDataUri::SVG && LogoDataUri::svgHasUnsafeContent($parsed['bytes'])) {
            $fail('The SVG logo contains scripts, event handlers or external references, which are not allowed.');
        }
    }
}
