<?php

namespace App\Livewire\Concerns;

use App\Documents\Data\TaxRate;
use App\Documents\DocumentDefaults;
use App\Documents\Validation\DocumentRules;

/**
 * @property array<string, mixed> $document
 */
trait ManagesTaxRates
{
    public function addTaxRate(): void
    {
        if (count($this->taxRates()) >= DocumentRules::MAX_TAX_RATES) {
            return;
        }

        $this->document['tax_rates'][] = DocumentDefaults::taxRate();
        $this->dirty = true;
    }

    /**
     * Remove a tax rate; any items using it fall back to "No tax".
     */
    public function removeTaxRate(string $id): void
    {
        $this->document['tax_rates'] = array_values(array_filter(
            $this->taxRates(),
            fn (array $rate): bool => $rate['id'] !== $id,
        ));

        foreach ($this->document['items'] as $index => $item) {
            if (($item['tax_rate_id'] ?? null) === $id) {
                $this->document['items'][$index]['tax_rate_id'] = TaxRate::NONE_ID;
            }
        }

        $this->dirty = true;
    }

    /**
     * The rate new items get: the first user-defined rate, or "No tax" if there are none.
     */
    private function defaultTaxRateId(): string
    {
        return $this->taxRates()[0]['id'] ?? TaxRate::NONE_ID;
    }

    /**
     * @return list<array{id: string, name: string, rate_percent: string}>
     */
    private function taxRates(): array
    {
        /** @var list<array{id: string, name: string, rate_percent: string}> */
        return $this->document['tax_rates'] ?? [];
    }
}
