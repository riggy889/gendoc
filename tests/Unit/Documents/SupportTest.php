<?php

use App\Documents\Support\CurrencyOptions;
use App\Documents\Support\MoneyInput;
use Brick\Money\Currency;

describe('MoneyInput', function () {
    it('parses typed amounts into minor units', function (string $input, string $currency, ?int $expected) {
        expect(MoneyInput::toMinor($input, Currency::of($currency)))->toBe($expected);
    })->with([
        ['12.50', 'GBP', 1250],
        ['1,250.5', 'GBP', 125050],
        [' 99 ', 'GBP', 9900],
        ['', 'GBP', 0],
        ['1250', 'JPY', 1250],
        ['1.234', 'BHD', 1234],
        'too many decimals' => ['12.345', 'GBP', null],
        'decimals in a zero-decimal currency' => ['12.5', 'JPY', null],
        'negative' => ['-5', 'GBP', null],
        'not a number' => ['abc', 'GBP', null],
    ]);

    it('rounds instead of rejecting when not strict', function () {
        expect(MoneyInput::toMinor('12.345', Currency::of('GBP'), strict: false))->toBe(1235)
            ->and(MoneyInput::toMinor('12.5', Currency::of('JPY'), strict: false))->toBe(13);
    });

    it('formats minor units for inputs', function () {
        expect(MoneyInput::fromMinor(1250, Currency::of('GBP')))->toBe('12.50')
            ->and(MoneyInput::fromMinor(1250, Currency::of('JPY')))->toBe('1250')
            ->and(MoneyInput::fromMinor(5, Currency::of('BHD')))->toBe('0.005');
    });
});

describe('CurrencyOptions', function () {
    it('lists common currencies first, then the rest alphabetically, with symbols', function () {
        $options = CurrencyOptions::grouped();

        expect(array_slice(array_keys($options['common']), 0, 5))->toBe(['GBP', 'EUR', 'USD', 'CAD', 'AUD'])
            ->and($options['common']['GBP'])->toBe('GBP (£) – Pound Sterling')
            ->and($options['other'])->not->toHaveKey('GBP')
            ->and($options['other'])->not->toHaveKey('DEM')
            ->and(array_keys($options['other']))->toBe(collect($options['other'])->keys()->sort()->values()->all());
    });
});
