<?php

namespace App\Documents\Data;

use Brick\Money\Money;
use InvalidArgumentException;

final readonly class DocumentTotals
{
    /**
     * @param  array<string, LineTotal>  $lines  keyed by item id
     * @param  Money  $subtotal  sum of the line amounts after line discounts
     * @param  Money  $net  subtotal minus the document discount
     * @param  list<TaxTotal>  $taxes  one entry per tax rate in use, excluding "No tax"
     */
    public function __construct(
        public array $lines,
        public Money $subtotal,
        public Money $documentDiscount,
        public Money $net,
        public array $taxes,
        public Money $total,
        public string $totalLabel,
    ) {}

    public function line(string $itemId): LineTotal
    {
        return $this->lines[$itemId] ?? throw new InvalidArgumentException("Unknown line item [{$itemId}].");
    }

    public function hasLineDiscounts(): bool
    {
        foreach ($this->lines as $line) {
            if (! $line->discount->isZero()) {
                return true;
            }
        }

        return false;
    }

    public function hasDocumentDiscount(): bool
    {
        return ! $this->documentDiscount->isZero();
    }

    public function taxTotal(): Money
    {
        return array_reduce(
            $this->taxes,
            fn (Money $carry, TaxTotal $tax): Money => $carry->plus($tax->tax),
            $this->net->multipliedBy(0),
        );
    }
}
