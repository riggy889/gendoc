<?php

namespace App\Documents\Data;

use Brick\Money\Money;

final readonly class TaxTotal
{
    public function __construct(
        public TaxRate $taxRate,
        public Money $taxable,
        public Money $tax,
    ) {}
}
