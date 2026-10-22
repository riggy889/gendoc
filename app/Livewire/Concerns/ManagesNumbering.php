<?php

namespace App\Livewire\Concerns;

use App\Documents\Data\NumberSeries;
use App\Documents\Enums\DocumentType;

/**
 * Per-type number prefixes and counters. The browser stores them (see resources/js/editor.js)
 * and hands them over on load; the counter only moves after a successful PDF download.
 *
 * @property array<string, mixed> $document
 */
trait ManagesNumbering
{
    /** @var array<string, array{prefix: string, highest: int}> keyed by document type */
    public array $numbering = [];

    /**
     * Record a downloaded PDF's number, so the next document of that type gets the number after it.
     */
    public function recordDownload(string $type, string $number): void
    {
        $documentType = DocumentType::tryFrom($type);

        if ($documentType === null) {
            return;
        }

        $this->numbering[$type] = $this->series($documentType)->record(trim($number))->toArray();

        if ($this->document['type'] === $type && trim((string) $this->document['number']) === trim($number)) {
            $this->dirty = false;
        }
    }

    /**
     * Keep the document number in step while its type's prefix is edited, if it uses the old prefix.
     */
    public function updatingNumbering(mixed $value, string $key): void
    {
        [$type, $field] = array_pad(explode('.', $key, 2), 2, null);
        $documentType = DocumentType::tryFrom((string) $type);

        if ($field !== 'prefix' || $documentType === null || $type !== $this->document['type'] || ! is_string($value)) {
            return;
        }

        $old = $this->series($documentType);
        $sequence = $old->sequenceOf((string) $this->document['number']);

        if ($sequence !== null) {
            $digits = substr((string) $this->document['number'], strlen($old->prefix));
            $this->document['number'] = $old->withPrefix($value)->prefix.$digits;
        }
    }

    public function updatedNumbering(): void
    {
        $this->numbering = $this->sanitisedNumbering($this->numbering);
    }

    private function series(DocumentType $type): NumberSeries
    {
        return NumberSeries::fromArray($type, $this->numbering[$type->value] ?? null);
    }

    private function nextNumber(DocumentType $type): string
    {
        return $this->series($type)->next();
    }

    /**
     * @return array<string, array{prefix: string, highest: int}>
     */
    private function sanitisedNumbering(mixed $numbering): array
    {
        $numbering = is_array($numbering) ? $numbering : [];
        $clean = [];

        foreach (DocumentType::cases() as $type) {
            $clean[$type->value] = NumberSeries::fromArray($type, $numbering[$type->value] ?? null)->toArray();
        }

        return $clean;
    }
}
