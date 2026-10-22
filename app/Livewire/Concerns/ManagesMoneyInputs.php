<?php

namespace App\Livewire\Concerns;

use App\Documents\DocumentDefaults;
use App\Documents\Enums\DiscountType;
use App\Documents\Support\MoneyInput;
use Brick\Money\Currency;
use Brick\Money\Exception\UnknownCurrencyException;

/**
 * Money is stored in minor units, but people type amounts like "12.50". These inputs hold the
 * typed text, keyed by item id, and write minor units back into the document as they change.
 *
 * @property array<string, mixed> $document
 */
trait ManagesMoneyInputs
{
    /** @var array<string, string> unit price inputs, keyed by item id */
    public array $prices = [];

    /** @var array<string, string> fixed line discount inputs, keyed by item id */
    public array $lineDiscountAmounts = [];

    public string $documentDiscountAmount = '';

    public function updatedPrices(mixed $value, string $id): void
    {
        $this->applyMoneyInput("prices.{$id}", (string) $value, function (int $minor) use ($id): void {
            $index = $this->itemIndex($id);
            if ($index !== null) {
                $this->document['items'][$index]['unit_price_minor'] = $minor;
            }
        });
    }

    public function updatedLineDiscountAmounts(mixed $value, string $id): void
    {
        $this->applyMoneyInput("lineDiscountAmounts.{$id}", (string) $value, function (int $minor) use ($id): void {
            $index = $this->itemIndex($id);
            if ($index !== null && ($this->document['items'][$index]['discount']['type'] ?? null) === DiscountType::Fixed->value) {
                $this->document['items'][$index]['discount']['value'] = $minor;
            }
        });
    }

    public function updatedDocumentDiscountAmount(mixed $value): void
    {
        $this->applyMoneyInput('documentDiscountAmount', (string) $value, function (int $minor): void {
            if (($this->document['document_discount']['type'] ?? null) === DiscountType::Fixed->value) {
                $this->document['document_discount']['value'] = $minor;
            }
        });
    }

    /**
     * Rebuild every money input from the document's minor units.
     */
    private function syncMoneyInputs(): void
    {
        $this->prices = [];
        $this->lineDiscountAmounts = [];

        foreach ($this->items() as $item) {
            $this->syncItemMoneyInputs($item);
        }

        $discount = $this->document['document_discount'] ?? null;
        $this->documentDiscountAmount = ($discount['type'] ?? null) === DiscountType::Fixed->value
            ? MoneyInput::fromMinor((int) $discount['value'], $this->currency())
            : '';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function syncItemMoneyInputs(array $item): void
    {
        $id = (string) $item['id'];
        $this->prices[$id] = MoneyInput::fromMinor((int) $item['unit_price_minor'], $this->currency());

        $discount = $item['discount'] ?? null;
        $this->lineDiscountAmounts[$id] = is_array($discount) && ($discount['type'] ?? null) === DiscountType::Fixed->value
            ? MoneyInput::fromMinor((int) $discount['value'], $this->currency())
            : '';
    }

    private function forgetItemMoneyInputs(string $id): void
    {
        unset($this->prices[$id], $this->lineDiscountAmounts[$id]);
    }

    /**
     * Re-read every typed amount in a new currency, so "12.50" stays 12.50 rather than the same
     * number of minor units. Amounts with too many decimals for the new currency are rounded.
     */
    private function convertMoneyInputsTo(Currency $currency): void
    {
        foreach ($this->items() as $index => $item) {
            $id = (string) $item['id'];
            $this->document['items'][$index]['unit_price_minor'] = MoneyInput::toMinor($this->prices[$id] ?? '', $currency, strict: false) ?? 0;

            if (($item['discount']['type'] ?? null) === DiscountType::Fixed->value) {
                $this->document['items'][$index]['discount']['value'] = MoneyInput::toMinor($this->lineDiscountAmounts[$id] ?? '', $currency, strict: false) ?? 0;
            }
        }

        if (($this->document['document_discount']['type'] ?? null) === DiscountType::Fixed->value) {
            $this->document['document_discount']['value'] = MoneyInput::toMinor($this->documentDiscountAmount, $currency, strict: false) ?? 0;
        }

        $this->syncMoneyInputs();
    }

    /**
     * @param  callable(int): void  $apply
     */
    private function applyMoneyInput(string $field, string $value, callable $apply): void
    {
        $currency = $this->currency();
        $minor = MoneyInput::toMinor($value, $currency);

        if ($minor === null) {
            $places = $currency->getDefaultFractionDigits();
            $this->moneyInputErrors[$field] = $places === 0
                ? 'Enter a whole amount, e.g. 1250.'
                : "Enter an amount with up to {$places} decimal places, e.g. 12.50.";

            return;
        }

        unset($this->moneyInputErrors[$field]);
        $apply($minor);
    }

    private function currency(): Currency
    {
        try {
            return Currency::of((string) ($this->document['currency'] ?? DocumentDefaults::CURRENCY));
        } catch (UnknownCurrencyException) {
            return Currency::of(DocumentDefaults::CURRENCY);
        }
    }
}
