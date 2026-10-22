<?php

namespace App\Livewire\Concerns;

use App\Documents\DocumentDefaults;
use App\Documents\Enums\DiscountType;
use App\Documents\Validation\DocumentRules;
use Illuminate\Support\Str;

/**
 * @property array<string, mixed> $document
 */
trait ManagesLineItems
{
    public function addItem(): void
    {
        if (count($this->items()) >= DocumentRules::MAX_ITEMS) {
            return;
        }

        $item = DocumentDefaults::item($this->defaultTaxRateId());
        $this->document['items'][] = $item;
        $this->syncItemMoneyInputs($item);
        $this->dirty = true;
    }

    public function removeItem(string $id): void
    {
        $index = $this->itemIndex($id);

        if ($index === null) {
            return;
        }

        array_splice($this->document['items'], $index, 1);
        $this->forgetItemMoneyInputs($id);
        $this->dirty = true;
    }

    public function duplicateItem(string $id): void
    {
        $index = $this->itemIndex($id);

        if ($index === null || count($this->items()) >= DocumentRules::MAX_ITEMS) {
            return;
        }

        $copy = ['id' => (string) Str::uuid()] + $this->items()[$index];
        array_splice($this->document['items'], $index + 1, 0, [$copy]);
        $this->syncItemMoneyInputs($copy);
        $this->dirty = true;
    }

    /**
     * Move an item up (-1) or down (+1); used by the keyboard-accessible buttons.
     */
    public function moveItem(string $id, int $offset): void
    {
        $index = $this->itemIndex($id);

        if ($index !== null) {
            $this->sortItem($id, $index + $offset);
        }
    }

    /**
     * Move an item to a new position; used by drag-and-drop (wire:sort).
     */
    public function sortItem(string $id, int $position): void
    {
        $index = $this->itemIndex($id);
        $position = max(0, min($position, count($this->items()) - 1));

        if ($index === null || $index === $position) {
            return;
        }

        $item = array_splice($this->document['items'], $index, 1);
        array_splice($this->document['items'], $position, 0, $item);
        $this->dirty = true;
    }

    public function setItemDiscountType(string $id, string $type): void
    {
        $index = $this->itemIndex($id);

        if ($index === null) {
            return;
        }

        $this->document['items'][$index]['discount'] = $this->newDiscount(DiscountType::tryFrom($type));
        $this->syncItemMoneyInputs($this->document['items'][$index]);
        $this->dirty = true;
    }

    /**
     * @return array{type: string, value: string|int}|null
     */
    private function newDiscount(?DiscountType $type): ?array
    {
        return match ($type) {
            DiscountType::Percent => ['type' => $type->value, 'value' => '0'],
            DiscountType::Fixed => ['type' => $type->value, 'value' => 0],
            null => null,
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(): array
    {
        /** @var list<array<string, mixed>> */
        return $this->document['items'] ?? [];
    }

    private function itemIndex(string $id): ?int
    {
        foreach ($this->items() as $index => $item) {
            if (($item['id'] ?? null) === $id) {
                return $index;
            }
        }

        return null;
    }
}
