<?php

namespace App\Documents;

use App\Documents\Data\Discount;
use App\Documents\Data\DocumentData;
use App\Documents\Data\DocumentTotals;
use App\Documents\Data\LineTotal;
use App\Documents\Data\TaxRate;
use App\Documents\Data\TaxTotal;
use App\Documents\Enums\DiscountType;
use Brick\Math\BigInteger;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use Brick\Money\Currency;
use Brick\Money\Money;

/**
 * Calculates document totals. All prices are net; tax is added on top.
 *
 * Order of operations:
 *  1. Line amount = quantity × unit price, rounded half-up to minor units.
 *  2. Line discount (percent rounded half-up, fixed clamped to the line amount).
 *  3. Document discount on the subtotal, allocated proportionally across lines.
 *  4. Tax per tax-rate group on that group's taxable amount, rounded half-up once per group.
 */
final class DocumentCalculator
{
    public function calculate(DocumentData $document): DocumentTotals
    {
        $currency = Currency::of($document->currency);
        $zero = Money::zero($currency);

        $amounts = [];
        $discounts = [];
        $nets = [];

        foreach ($document->items as $index => $item) {
            $amount = Money::ofMinor($item->unitPriceMinor, $currency)
                ->multipliedBy($item->quantity, RoundingMode::HalfUp);
            $discount = $this->discountAmount($item->discount, $amount);

            $amounts[$index] = $amount;
            $discounts[$index] = $discount;
            $nets[$index] = $amount->minus($discount);
        }

        $subtotal = $this->sum($nets, $zero);
        $documentDiscount = $this->discountAmount($document->documentDiscount, $subtotal);
        $shares = $this->allocate($documentDiscount, $nets);

        $lines = [];

        foreach ($document->items as $index => $item) {
            $lines[$item->id] = new LineTotal(
                itemId: $item->id,
                taxRate: $document->taxRate($item->taxRateId),
                amount: $amounts[$index],
                discount: $discounts[$index],
                net: $nets[$index],
                documentDiscount: $shares[$index],
                taxable: $nets[$index]->minus($shares[$index]),
            );
        }

        $net = $subtotal->minus($documentDiscount);
        $taxes = $this->taxes($document->taxRates, $lines, $zero);

        $total = $net;
        foreach ($taxes as $tax) {
            $total = $total->plus($tax->tax);
        }

        return new DocumentTotals(
            lines: $lines,
            subtotal: $subtotal,
            documentDiscount: $documentDiscount,
            net: $net,
            taxes: $taxes,
            total: $total,
            totalLabel: $document->type->totalLabel(),
        );
    }

    /**
     * The discount amount for a base amount, never more than the base itself.
     */
    private function discountAmount(?Discount $discount, Money $base): Money
    {
        if ($discount === null || $base->isZero()) {
            return $base->multipliedBy(0);
        }

        $amount = $discount->type === DiscountType::Percent
            ? $base->multipliedBy(BigRational::of($discount->value)->dividedBy(100), RoundingMode::HalfUp)
            : Money::ofMinor($discount->amountMinor(), $base->getCurrency());

        return $amount->isGreaterThan($base) ? $base : $amount;
    }

    /**
     * Split the document discount across lines in proportion to their net amounts.
     *
     * Each share is rounded half-up; any remainder goes on the largest line so the shares add up
     * exactly. If the largest line can't absorb all of it (no share may exceed its line or go
     * below zero), the rest moves on to the next largest line.
     *
     * @param  array<int, Money>  $nets
     * @return array<int, Money>
     */
    private function allocate(Money $total, array $nets): array
    {
        $currency = $total->getCurrency();
        $netsMinor = array_map(fn (Money $net): BigInteger => $this->minor($net), $nets);
        $subtotalMinor = array_reduce($netsMinor, fn (BigInteger $carry, BigInteger $net): BigInteger => $carry->plus($net), BigInteger::zero());
        $totalMinor = $this->minor($total);

        if ($totalMinor->isZero() || $subtotalMinor->isZero()) {
            return array_map(fn (): Money => Money::zero($currency), $nets);
        }

        $shares = array_map(
            fn (BigInteger $net): BigInteger => $totalMinor->multipliedBy($net)
                ->toBigDecimal()
                ->dividedBy($subtotalMinor, 0, RoundingMode::HalfUp)
                ->toBigInteger(),
            $netsMinor,
        );

        $remainder = $totalMinor->minus(array_reduce($shares, fn (BigInteger $carry, BigInteger $share): BigInteger => $carry->plus($share), BigInteger::zero()));

        foreach ($this->indexesByLargest($netsMinor) as $index) {
            if ($remainder->isZero()) {
                break;
            }

            // A positive remainder fills the line up to its net; a negative one drains it to zero.
            $room = $remainder->isPositive() ? $netsMinor[$index]->minus($shares[$index]) : $shares[$index]->negated();
            $adjustment = $remainder->isPositive() ? BigInteger::min($remainder, $room) : BigInteger::max($remainder, $room);

            $shares[$index] = $shares[$index]->plus($adjustment);
            $remainder = $remainder->minus($adjustment);
        }

        return array_map(fn (BigInteger $share): Money => Money::ofMinor($share, $currency), $shares);
    }

    /**
     * Line indexes ordered by net amount, largest first; equal amounts keep their original order.
     *
     * @param  array<int, BigInteger>  $netsMinor
     * @return list<int>
     */
    private function indexesByLargest(array $netsMinor): array
    {
        $indexes = array_keys($netsMinor);

        usort($indexes, fn (int $a, int $b): int => $netsMinor[$b]->compareTo($netsMinor[$a]) ?: $a <=> $b);

        return $indexes;
    }

    /**
     * One tax total per user-defined rate that has lines, in the order the rates are defined.
     * Lines with "No tax" contribute to the net but produce no tax line.
     *
     * @param  list<TaxRate>  $taxRates
     * @param  array<string, LineTotal>  $lines
     * @return list<TaxTotal>
     */
    private function taxes(array $taxRates, array $lines, Money $zero): array
    {
        $taxes = [];

        foreach ($taxRates as $taxRate) {
            $groupLines = array_filter($lines, fn (LineTotal $line): bool => $line->taxRate->id === $taxRate->id);

            if ($groupLines === []) {
                continue;
            }

            $taxable = $this->sum(array_map(fn (LineTotal $line): Money => $line->taxable, $groupLines), $zero);
            $tax = $taxable->multipliedBy(BigRational::of($taxRate->ratePercent)->dividedBy(100), RoundingMode::HalfUp);

            $taxes[] = new TaxTotal($taxRate, $taxable, $tax);
        }

        return $taxes;
    }

    /**
     * @param  array<array-key, Money>  $amounts
     */
    private function sum(array $amounts, Money $zero): Money
    {
        return array_reduce($amounts, fn (Money $carry, Money $amount): Money => $carry->plus($amount), $zero);
    }

    private function minor(Money $money): BigInteger
    {
        return $money->getMinorAmount()->toBigInteger();
    }
}
