<?php

namespace App\Documents\Data;

final readonly class TaxRate
{
    /**
     * The reserved id of the built-in "No tax" rate, which is never stored in a document's tax_rates.
     */
    public const NONE_ID = 'none';

    public function __construct(
        public string $id,
        public string $name,
        public string $ratePercent,
    ) {}

    public static function none(): self
    {
        return new self(self::NONE_ID, 'No tax', '0');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            name: (string) $data['name'],
            ratePercent: Decimal::normalise((string) $data['rate_percent']),
        );
    }

    public function isNone(): bool
    {
        return $this->id === self::NONE_ID;
    }

    /**
     * The label shown on the document, e.g. "VAT 20%".
     */
    public function label(): string
    {
        return "{$this->name} {$this->ratePercent}%";
    }

    /**
     * @return array{id: string, name: string, rate_percent: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'rate_percent' => $this->ratePercent,
        ];
    }
}
