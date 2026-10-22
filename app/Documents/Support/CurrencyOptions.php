<?php

namespace App\Documents\Support;

use Brick\Money\Currency;
use Brick\Money\CurrencyType;
use Brick\Money\IsoCurrencyProvider;
use NumberFormatter;

/**
 * The options for the currency dropdown: common currencies first, then all others alphabetically.
 */
final class CurrencyOptions
{
    /** @var list<string> */
    public const COMMON = ['GBP', 'EUR', 'USD', 'CAD', 'AUD', 'NZD', 'CHF', 'JPY', 'SEK', 'NOK', 'DKK', 'INR', 'ZAR', 'SGD', 'HKD'];

    /** @var array{common: array<string, string>, other: array<string, string>}|null */
    private static ?array $grouped = null;

    /**
     * @return array{common: array<string, string>, other: array<string, string>} labels keyed by currency code
     */
    public static function grouped(): array
    {
        return self::$grouped ??= self::build();
    }

    public static function isSupported(string $code): bool
    {
        $grouped = self::grouped();

        return isset($grouped['common'][$code]) || isset($grouped['other'][$code]);
    }

    /**
     * @return array{common: array<string, string>, other: array<string, string>}
     */
    private static function build(): array
    {
        $currencies = array_filter(
            IsoCurrencyProvider::getInstance()->getAvailableCurrencies(),
            fn (Currency $currency): bool => $currency->getCurrencyType() === CurrencyType::IsoCurrent,
        );

        $common = [];
        foreach (self::COMMON as $code) {
            $common[$code] = self::label($currencies[$code]);
        }

        $other = [];
        foreach (array_diff_key($currencies, $common) as $code => $currency) {
            $other[$code] = self::label($currency);
        }

        return ['common' => $common, 'other' => $other];
    }

    public static function symbol(string $code): string
    {
        $formatter = new NumberFormatter("en@currency={$code}", NumberFormatter::CURRENCY);
        $symbol = $formatter->getSymbol(NumberFormatter::CURRENCY_SYMBOL);

        return $symbol === '' ? $code : $symbol;
    }

    private static function label(Currency $currency): string
    {
        $code = $currency->getCurrencyCode();
        $symbol = self::symbol($code);

        return $symbol === $code
            ? "{$code} – {$currency->getName()}"
            : "{$code} ({$symbol}) – {$currency->getName()}";
    }
}
