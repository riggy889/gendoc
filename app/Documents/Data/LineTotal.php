<?php

namespace App\Documents\Data;

use Brick\Money\Money;

final readonly class LineTotal
{
    /**
     * @param  Money  $amount  quantity × unit price, rounded to minor units
     * @param  Money  $discount  the line's own discount
     * @param  Money  $net  amount minus the line discount (shown on the row)
     * @param  Money  $documentDiscount  this line's share of the document discount
     * @param  Money  $taxable  net minus the document discount share
     */
    public function __construct(
        public string $itemId,
        public TaxRate $taxRate,
        public Money $amount,
        public Money $discount,
        public Money $net,
        public Money $documentDiscount,
        public Money $taxable,
    ) {}
}
