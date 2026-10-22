<?php

namespace App\Documents;

use App\Documents\Enums\DocumentType;
use App\Documents\Validation\DocumentRules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

/**
 * Turns any draft (half-typed editor state, or JSON from localStorage) into a payload that passes
 * DocumentRules. Invalid fields are replaced with safe fallbacks rather than rejected, so the
 * preview can always render. If the draft can't be repaired it falls back to a blank document.
 */
final class DraftSanitiser
{
    /**
     * Fallbacks that differ from a new document's defaults: a broken value is shown as empty
     * rather than replaced with something the user didn't type.
     *
     * @var array<string, mixed>
     */
    private const FIELD_FALLBACKS = [
        'number' => '',
        'due_date' => null,
        'valid_until' => null,
        'original_invoice_number' => null,
    ];

    /** @var array<string, mixed> */
    private const ITEM_FALLBACKS = [
        'description' => '',
        'quantity' => '0',
        'unit_price_minor' => 0,
        'tax_rate_id' => 'none',
        'discount' => null,
    ];

    /** @var array<string, mixed> */
    private const TAX_RATE_FALLBACKS = [
        'name' => 'Tax',
        'rate_percent' => '0',
    ];

    /**
     * @param  array<array-key, mixed>  $draft
     * @return array<string, mixed>
     */
    public function sanitise(array $draft, ?CarbonImmutable $today = null): array
    {
        $type = DocumentType::tryFrom(is_string($draft['type'] ?? null) ? $draft['type'] : '') ?? DocumentType::Invoice;
        $defaults = DocumentDefaults::document($type, $today ?? CarbonImmutable::today());

        $payload = $this->shape($draft, $defaults);
        $payload = $this->repair($payload, $defaults);

        // Blank required fields (e.g. no number yet) are fine for a draft; anything else means it is corrupt.
        $remaining = array_diff($this->errors($payload), array_keys(self::FIELD_FALLBACKS));

        return $remaining === [] ? $payload : $defaults;
    }

    /**
     * Keep only known keys, with each section in the right structural shape.
     *
     * @param  array<array-key, mixed>  $draft
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function shape(array $draft, array $defaults): array
    {
        $payload = [];

        foreach ($defaults as $key => $default) {
            $value = $draft[$key] ?? $default;

            $payload[$key] = match ($key) {
                'from', 'to', 'style' => is_array($value) ? array_intersect_key($value, $default) + $default : $default,
                'tax_rates', 'items' => is_array($value) ? array_values(array_filter($value, 'is_array')) : [],
                default => $value,
            };
        }

        $payload['items'] = array_map(
            fn (array $item): array => array_intersect_key($item + self::ITEM_FALLBACKS, self::ITEM_FALLBACKS + ['id' => null]),
            $payload['items'],
        );
        $payload['tax_rates'] = array_map(
            fn (array $rate): array => array_intersect_key($rate + self::TAX_RATE_FALLBACKS, self::TAX_RATE_FALLBACKS + ['id' => null]),
            $payload['tax_rates'],
        );

        return $payload;
    }

    /**
     * Replace each invalid field with its fallback. Invalid list entries that can't be repaired
     * (e.g. a bad id) are dropped, and over-long lists are truncated.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function repair(array $payload, array $defaults): array
    {
        $drop = ['items' => [], 'tax_rates' => []];

        foreach ($this->errors($payload) as $key) {
            if (preg_match('/^(items|tax_rates)\.(\d+)(?:\.([a-z_]+))?/', $key, $matches)) {
                [, $list, $index] = $matches;
                $field = $matches[3] ?? null;
                $fallbacks = $list === 'items' ? self::ITEM_FALLBACKS : self::TAX_RATE_FALLBACKS;

                if ($field !== null && array_key_exists($field, $fallbacks)) {
                    $payload[$list][(int) $index][$field] = $fallbacks[$field];
                } else {
                    $drop[$list][] = (int) $index;
                }

                continue;
            }

            if ($key === 'items' || $key === 'tax_rates') {
                $max = $key === 'items' ? DocumentRules::MAX_ITEMS : DocumentRules::MAX_TAX_RATES;
                $payload[$key] = array_slice(is_array($payload[$key]) ? $payload[$key] : [], 0, $max);

                continue;
            }

            Arr::set($payload, $key, array_key_exists($key, self::FIELD_FALLBACKS) ? self::FIELD_FALLBACKS[$key] : Arr::get($defaults, $key));
        }

        foreach ($drop as $list => $indexes) {
            $payload[$list] = array_values(array_diff_key($payload[$list], array_flip($indexes)));
        }

        // Dropping a tax rate may orphan items, which is fine: DocumentData treats unknown ids as "No tax".
        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function errors(array $payload): array
    {
        return array_values(Validator::make($payload, DocumentRules::rules())->errors()->keys());
    }
}
