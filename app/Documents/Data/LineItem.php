<?php

namespace App\Documents\Data;

final readonly class LineItem
{
    public function __construct(
        public string $id,
        public string $description,
        public string $quantity,
        public int $unitPriceMinor,
        public string $taxRateId,
        public ?Discount $discount = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed>|null $discount */
        $discount = $data['discount'] ?? null;

        return new self(
            id: (string) $data['id'],
            description: (string) ($data['description'] ?? ''),
            quantity: Decimal::normalise((string) $data['quantity']),
            unitPriceMinor: (int) $data['unit_price_minor'],
            taxRateId: (string) $data['tax_rate_id'],
            discount: Discount::fromArray($discount),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price_minor' => $this->unitPriceMinor,
            'tax_rate_id' => $this->taxRateId,
            'discount' => $this->discount?->toArray(),
        ];
    }
}
