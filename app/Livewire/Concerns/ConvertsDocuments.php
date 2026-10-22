<?php

namespace App\Livewire\Concerns;

use App\Documents\DocumentDefaults;
use App\Documents\Enums\DocumentType;
use Carbon\CarbonImmutable;

/**
 * Turning a quote into an invoice, and raising a credit note against an invoice.
 *
 * @property array<string, mixed> $document
 */
trait ConvertsDocuments
{
    /**
     * Copy the quote into a new invoice dated today, with its due date from the payment terms.
     */
    public function convertToInvoice(): void
    {
        if ($this->document['type'] !== DocumentType::Quote->value) {
            return;
        }

        $today = CarbonImmutable::today();
        $days = $this->document['payment_terms_days'] ?? null;

        $this->startFrom(DocumentType::Invoice, [
            'due_date' => $today->addDays(is_int($days) ? $days : DocumentDefaults::PAYMENT_TERMS_DAYS)->format('Y-m-d'),
        ]);
    }

    /**
     * Copy the invoice's items into a new credit note that refers back to it.
     */
    public function createCreditNote(): void
    {
        if ($this->document['type'] !== DocumentType::Invoice->value) {
            return;
        }

        $this->startFrom(DocumentType::CreditNote, [
            'original_invoice_number' => $this->document['number'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function startFrom(DocumentType $type, array $changes): void
    {
        $this->document = [
            ...$this->document,
            'type' => $type->value,
            'number' => $this->nextNumber($type),
            'issue_date' => CarbonImmutable::today()->format('Y-m-d'),
            'due_date' => null,
            'valid_until' => null,
            'original_invoice_number' => null,
            ...$changes,
        ];

        $this->dirty = true;
    }
}
