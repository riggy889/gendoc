<?php

namespace App\Documents\Data;

use App\Documents\Enums\DiscountType;
use Brick\Math\BigDecimal;

/**
 * A line or document discount. For percent discounts the value is a decimal string
 * (e.g. "12.5"); for fixed discounts it is an integer amount in minor units.
 */
final readonly class Discount
{
    private function __construct(
        public DiscountType $type,
        public string $value,
    ) {}

    public static function percent(string $percent): self
    {
        return new self(DiscountType::Percent, Decimal::normalise($percent));
    }

    public static function fixed(int $amountMinor): self
    {
        return new self(DiscountType::Fixed, (string) $amountMinor);
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function fromArray(?array $data): ?self
    {
        if ($data === null) {
            return null;
        }

        $type = DiscountType::from((string) $data['type']);

        return $type === DiscountType::Percent
            ? self::percent((string) $data['value'])
            : self::fixed((int) $data['value']);
    }

    public function percentage(): BigDecimal
    {
        return BigDecimal::of($this->value);
    }

    public function amountMinor(): int
    {
        return (int) $this->value;
    }

    /**
     * @return array{type: string, value: string|int}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'value' => $this->type === DiscountType::Percent ? $this->value : $this->amountMinor(),
        ];
    }
}
