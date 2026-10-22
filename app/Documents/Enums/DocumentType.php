<?php

namespace App\Documents\Enums;

enum DocumentType: string
{
    case Quote = 'quote';
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';

    public function title(): string
    {
        return match ($this) {
            self::Quote => 'Quote',
            self::Invoice => 'Invoice',
            self::CreditNote => 'Credit Note',
        };
    }

    public function totalLabel(): string
    {
        return match ($this) {
            self::Quote => 'Total',
            self::Invoice => 'Amount due',
            self::CreditNote => 'Total credit',
        };
    }

    /**
     * Label for the type-specific date: "Valid until" on quotes, "Due date" on invoices.
     */
    public function secondaryDateLabel(): ?string
    {
        return match ($this) {
            self::Quote => 'Valid until',
            self::Invoice => 'Due date',
            self::CreditNote => null,
        };
    }

    public function defaultNumberPrefix(): string
    {
        return match ($this) {
            self::Quote => 'QUO-',
            self::Invoice => 'INV-',
            self::CreditNote => 'CN-',
        };
    }

    /**
     * Heading for the customer's address block.
     */
    public function recipientLabel(): string
    {
        return match ($this) {
            self::Quote => 'Quote for',
            self::Invoice => 'Bill to',
            self::CreditNote => 'Credit to',
        };
    }
}
