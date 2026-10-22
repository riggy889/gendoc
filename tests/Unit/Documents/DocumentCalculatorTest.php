<?php

use App\Documents\Data\DocumentData;
use App\Documents\Data\DocumentTotals;
use App\Documents\Data\TaxTotal;
use App\Documents\DocumentCalculator;

/**
 * @param  array<string, mixed>  $overrides
 */
function totals(array $overrides = []): DocumentTotals
{
    return (new DocumentCalculator)->calculate(document($overrides));
}

/**
 * @return array<string, int>
 */
function taxesByLabel(DocumentTotals $totals): array
{
    return collect($totals->taxes)
        ->mapWithKeys(fn (TaxTotal $tax): array => [$tax->taxRate->label() => minor($tax->tax)])
        ->all();
}

/**
 * Assert every reconciliation rule: lines add up to the subtotal, discount shares add up to the
 * document discount, taxable amounts add up to the net, and net + tax = total.
 */
function assertReconciles(DocumentData $document, DocumentTotals $totals): void
{
    $lines = collect($totals->lines);

    expect($lines->sum(fn ($line) => minor($line->net)))->toBe(minor($totals->subtotal))
        ->and($lines->sum(fn ($line) => minor($line->documentDiscount)))->toBe(minor($totals->documentDiscount))
        ->and($lines->sum(fn ($line) => minor($line->taxable)))->toBe(minor($totals->net))
        ->and(minor($totals->subtotal) - minor($totals->documentDiscount))->toBe(minor($totals->net))
        ->and(minor($totals->net) + minor($totals->taxTotal()))->toBe(minor($totals->total));

    foreach ($totals->lines as $line) {
        expect(minor($line->documentDiscount))->toBeGreaterThanOrEqual(0)
            ->and(minor($line->documentDiscount))->toBeLessThanOrEqual(minor($line->net))
            ->and(minor($line->discount))->toBeLessThanOrEqual(minor($line->amount));
    }
}

describe('line amounts', function () {
    it('multiplies quantity by unit price', function () {
        $totals = totals(['items' => [lineItem('3', 1250)]]);

        expect(minor($totals->subtotal))->toBe(3750)
            ->and(minor($totals->taxes[0]->tax))->toBe(750)
            ->and(minor($totals->total))->toBe(4500);
    });

    it('rounds each line half-up to minor units', function (string $quantity, int $price, int $expected) {
        $item = lineItem($quantity, $price);

        expect(minor(totals(['items' => [$item]])->line($item['id'])->amount))->toBe($expected);
    })->with([
        '2.5 × £33.33 = £83.325 → £83.33' => ['2.5', 3333, 8333],
        'exactly half a penny rounds up' => ['0.5', 1, 1],
        'just under half rounds down' => ['0.4999', 1, 0],
        'four decimal quantity' => ['1.3333', 300, 400],
    ]);

    it('rounds lines individually before summing', function () {
        // Each line is £0.005 → £0.01, so two lines make £0.02 (not £0.01 from rounding the sum).
        $totals = totals(['items' => [lineItem('0.5', 1), lineItem('0.5', 1)]]);

        expect(minor($totals->subtotal))->toBe(2);
    });
});

describe('line discounts', function () {
    it('applies a percentage of the line amount, rounded half-up', function (string $percent, int $amount, int $expectedDiscount) {
        $item = lineItem('1', $amount, discount: ['type' => 'percent', 'value' => $percent]);
        $line = totals(['items' => [$item]])->line($item['id']);

        expect(minor($line->discount))->toBe($expectedDiscount)
            ->and(minor($line->net))->toBe($amount - $expectedDiscount);
    })->with([
        '10% of £83.33' => ['10', 8333, 833],
        '12.5% of £10.04 = 125.5p → 126p' => ['12.5', 1004, 126],
        '100% leaves nothing' => ['100', 999, 999],
        '0% does nothing' => ['0', 999, 0],
    ]);

    it('applies a fixed amount', function () {
        $item = lineItem('2', 1000, discount: ['type' => 'fixed', 'value' => 250]);
        $line = totals(['items' => [$item]])->line($item['id']);

        expect(minor($line->discount))->toBe(250)
            ->and(minor($line->net))->toBe(1750);
    });

    it('clamps a fixed discount so the line never goes below zero', function () {
        $item = lineItem('1', 1000, discount: ['type' => 'fixed', 'value' => 1500]);
        $totals = totals(['items' => [$item]]);

        expect(minor($totals->line($item['id'])->discount))->toBe(1000)
            ->and(minor($totals->line($item['id'])->net))->toBe(0)
            ->and(minor($totals->total))->toBe(0);
    });

    it('reports whether any line discounts exist', function () {
        expect(totals()->hasLineDiscounts())->toBeFalse()
            ->and(totals(['items' => [lineItem('1', 100, discount: ['type' => 'fixed', 'value' => 10])]])->hasLineDiscounts())->toBeTrue();
    });
});

describe('document discount', function () {
    it('applies after line discounts and before tax', function () {
        $totals = totals([
            'items' => [lineItem('1', 10000, discount: ['type' => 'percent', 'value' => '10'])],
            'document_discount' => ['type' => 'percent', 'value' => '10'],
        ]);

        // £100 - 10% = £90 subtotal; - 10% = £81 net; + 20% VAT = £97.20.
        expect(minor($totals->subtotal))->toBe(9000)
            ->and(minor($totals->documentDiscount))->toBe(900)
            ->and(minor($totals->net))->toBe(8100)
            ->and(minor($totals->taxes[0]->tax))->toBe(1620)
            ->and(minor($totals->total))->toBe(9720)
            ->and($totals->hasDocumentDiscount())->toBeTrue();
    });

    it('is allocated proportionally so tax per rate stays correct', function () {
        $vatLine = lineItem('1', 10000, 'vat20');
        $zeroLine = lineItem('1', 5000, 'none');
        $totals = totals([
            'items' => [$vatLine, $zeroLine],
            'document_discount' => ['type' => 'fixed', 'value' => 1500],
        ]);

        // £15 is split 2:1, so only £10 comes off the VAT-able line.
        expect(minor($totals->line($vatLine['id'])->documentDiscount))->toBe(1000)
            ->and(minor($totals->line($zeroLine['id'])->documentDiscount))->toBe(500)
            ->and(minor($totals->taxes[0]->taxable))->toBe(9000)
            ->and(minor($totals->taxes[0]->tax))->toBe(1800)
            ->and(minor($totals->total))->toBe(15300);
    });

    it('clamps a fixed amount to the subtotal', function () {
        $totals = totals([
            'items' => [lineItem('1', 1000)],
            'document_discount' => ['type' => 'fixed', 'value' => 5000],
        ]);

        expect(minor($totals->documentDiscount))->toBe(1000)
            ->and(minor($totals->net))->toBe(0)
            ->and(minor($totals->total))->toBe(0);
    });

    it('puts the rounding remainder on the largest line', function () {
        $small = lineItem('1', 100);
        $large = lineItem('1', 200);
        $other = lineItem('1', 100);
        // £1 over 1:2:1 is 25p / 50p / 25p exactly; make it uneven with 99p → 24.75 / 49.5 / 24.75.
        $totals = totals([
            'items' => [$small, $large, $other],
            'document_discount' => ['type' => 'fixed', 'value' => 99],
        ]);

        // Rounded half-up: 25 + 50 + 25 = 100, one too many, so the largest line gives one back.
        expect(minor($totals->line($small['id'])->documentDiscount))->toBe(25)
            ->and(minor($totals->line($large['id'])->documentDiscount))->toBe(49)
            ->and(minor($totals->line($other['id'])->documentDiscount))->toBe(25);
    });

    it('gives the remainder to the first of equally large lines', function () {
        $items = [lineItem('1', 100), lineItem('1', 100), lineItem('1', 100)];
        $totals = totals([
            'items' => $items,
            'document_discount' => ['type' => 'fixed', 'value' => 100],
        ]);

        // 33.33 each rounds to 33, leaving 1p for the first line.
        expect(array_map(fn ($item) => minor($totals->line($item['id'])->documentDiscount), $items))->toBe([34, 33, 33]);
    });

    it('moves a remainder on to the next line when the largest line cannot absorb it', function () {
        $items = [lineItem('1', 1), lineItem('1', 1), lineItem('1', 1), lineItem('1', 1)];
        $totals = totals([
            'items' => $items,
            'document_discount' => ['type' => 'fixed', 'value' => 2],
        ]);

        // Each share is 0.5p → 1p, giving 4p for a 2p discount; no share can go below zero.
        expect(array_map(fn ($item) => minor($totals->line($item['id'])->documentDiscount), $items))->toBe([0, 0, 1, 1])
            ->and(minor($totals->documentDiscount))->toBe(2);
    });

    it('reconciles exactly for many random documents', function () {
        mt_srand(20261022);

        for ($i = 0; $i < 300; $i++) {
            $items = [];
            for ($n = mt_rand(1, 8); $n > 0; $n--) {
                $discount = match (mt_rand(0, 2)) {
                    0 => null,
                    1 => ['type' => 'percent', 'value' => (string) (mt_rand(0, 10000) / 100)],
                    2 => ['type' => 'fixed', 'value' => mt_rand(0, 5000)],
                };
                $items[] = lineItem((string) (mt_rand(0, 100000) / 1000), mt_rand(0, 100000), ['vat20', 'vat5', 'none'][mt_rand(0, 2)], $discount);
            }

            $document = document([
                'currency' => ['GBP', 'JPY', 'BHD'][mt_rand(0, 2)],
                'tax_rates' => [
                    ['id' => 'vat20', 'name' => 'VAT', 'rate_percent' => '20'],
                    ['id' => 'vat5', 'name' => 'Reduced', 'rate_percent' => '5'],
                ],
                'items' => $items,
                'document_discount' => mt_rand(0, 1)
                    ? ['type' => 'fixed', 'value' => mt_rand(0, 50000)]
                    : ['type' => 'percent', 'value' => (string) (mt_rand(0, 10000) / 100)],
            ]);

            assertReconciles($document, (new DocumentCalculator)->calculate($document));
        }
    });
});

describe('tax', function () {
    it('produces one tax line per rate in use, in the order the rates are defined', function () {
        $totals = totals([
            'tax_rates' => [
                ['id' => 'vat20', 'name' => 'VAT', 'rate_percent' => '20'],
                ['id' => 'vat5', 'name' => 'VAT', 'rate_percent' => '5'],
                ['id' => 'unused', 'name' => 'Unused', 'rate_percent' => '10'],
            ],
            'items' => [lineItem('1', 1000, 'vat5'), lineItem('1', 2000, 'vat20'), lineItem('1', 500, 'none')],
        ]);

        expect(taxesByLabel($totals))->toBe(['VAT 20%' => 400, 'VAT 5%' => 50])
            ->and(minor($totals->net))->toBe(3500)
            ->and(minor($totals->total))->toBe(3950);
    });

    it('rounds tax once per group, not per line', function () {
        $totals = totals([
            'tax_rates' => [['id' => 'vat5', 'name' => 'VAT', 'rate_percent' => '5']],
            'items' => [lineItem('1', 333, 'vat5'), lineItem('1', 333, 'vat5'), lineItem('1', 333, 'vat5')],
        ]);

        // Per line: 16.65p → 17p × 3 = 51p. Per group: 999p × 5% = 49.95p → 50p.
        expect(minor($totals->taxes[0]->tax))->toBe(50);
    });

    it('supports rates with three decimal places', function () {
        $totals = totals([
            'tax_rates' => [['id' => 'ny', 'name' => 'Sales tax', 'rate_percent' => '8.875']],
            'items' => [lineItem('1', 10000, 'ny')],
        ]);

        expect(taxesByLabel($totals))->toBe(['Sales tax 8.875%' => 888]);
    });

    it('shows a user-defined zero rate but not the built-in "No tax" rate', function () {
        $totals = totals([
            'tax_rates' => [['id' => 'zero', 'name' => 'Zero-rated', 'rate_percent' => '0']],
            'items' => [lineItem('1', 1000, 'zero'), lineItem('1', 1000, 'none')],
        ]);

        expect(taxesByLabel($totals))->toBe(['Zero-rated 0%' => 0])
            ->and(minor($totals->total))->toBe(2000);
    });

    it('treats an unknown tax rate id as "No tax"', function () {
        $item = lineItem('1', 1000, 'deleted-rate');
        $totals = totals(['items' => [$item]]);

        expect($totals->taxes)->toBe([])
            ->and($totals->line($item['id'])->taxRate->isNone())->toBeTrue()
            ->and(minor($totals->total))->toBe(1000);
    });
});

describe('currencies', function () {
    it('handles zero-decimal currencies', function (string $currency) {
        $totals = totals([
            'currency' => $currency,
            'tax_rates' => [['id' => 'tax10', 'name' => 'Tax', 'rate_percent' => '10']],
            'items' => [lineItem('1.5', 333, 'tax10')],
        ]);

        // 1.5 × 333 = 499.5 → 500; 10% = 50; no fractional units anywhere.
        expect($totals->subtotal->getAmount()->getScale())->toBe(0)
            ->and(minor($totals->subtotal))->toBe(500)
            ->and(minor($totals->taxes[0]->tax))->toBe(50)
            ->and(minor($totals->total))->toBe(550);
    })->with(['JPY', 'KRW']);

    it('handles three-decimal currencies', function () {
        $totals = totals([
            'currency' => 'BHD',
            'tax_rates' => [['id' => 'vat10', 'name' => 'VAT', 'rate_percent' => '10']],
            'items' => [lineItem('1', 1235, 'vat10')],
        ]);

        // 1.235 BHD + 10% = 0.1235 → 0.124.
        expect($totals->total->getAmount()->getScale())->toBe(3)
            ->and(minor($totals->taxes[0]->tax))->toBe(124)
            ->and((string) $totals->total->getAmount())->toBe('1.359');
    });
});

describe('document types', function () {
    it('labels the total for each type', function (string $type, string $label) {
        expect(totals(['type' => $type])->totalLabel)->toBe($label);
    })->with([
        ['quote', 'Total'],
        ['invoice', 'Amount due'],
        ['credit_note', 'Total credit'],
    ]);

    it('shows positive amounts on credit notes', function () {
        $totals = totals(['type' => 'credit_note', 'items' => [lineItem('2', 5000)]]);

        expect(minor($totals->subtotal))->toBe(10000)
            ->and(minor($totals->total))->toBe(12000)
            ->and($totals->total->isPositive())->toBeTrue();
    });
});

describe('empty documents', function () {
    it('returns zero totals when there are no items', function () {
        $totals = totals([
            'items' => [],
            'document_discount' => ['type' => 'fixed', 'value' => 500],
        ]);

        expect(minor($totals->subtotal))->toBe(0)
            ->and(minor($totals->documentDiscount))->toBe(0)
            ->and(minor($totals->total))->toBe(0)
            ->and($totals->lines)->toBe([])
            ->and($totals->taxes)->toBe([]);
    });

    it('returns zero totals when every quantity is zero', function () {
        $totals = totals([
            'items' => [lineItem('0', 1000), lineItem('0', 2500)],
            'document_discount' => ['type' => 'percent', 'value' => '50'],
        ]);

        expect(minor($totals->documentDiscount))->toBe(0)
            ->and(minor($totals->total))->toBe(0)
            ->and(minor($totals->taxes[0]->tax))->toBe(0);
    });

    it('handles a document fully discounted to zero', function () {
        $document = document([
            'items' => [lineItem('1', 1000), lineItem('3', 333, 'none')],
            'document_discount' => ['type' => 'percent', 'value' => '100'],
        ]);
        $totals = (new DocumentCalculator)->calculate($document);

        expect(minor($totals->net))->toBe(0)
            ->and(minor($totals->total))->toBe(0);
        assertReconciles($document, $totals);
    });
});
