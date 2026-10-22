<?php

namespace App\Documents\Data;

use App\Documents\Enums\DocumentType;
use Carbon\CarbonImmutable;

final readonly class DocumentData
{
    /**
     * @param  list<TaxRate>  $taxRates  user-defined rates; the built-in "No tax" rate is not included
     * @param  list<LineItem>  $items
     */
    public function __construct(
        public DocumentType $type,
        public string $number,
        public CarbonImmutable $issueDate,
        public string $currency,
        public BusinessDetails $from,
        public CustomerDetails $to,
        public array $taxRates,
        public array $items,
        public DocumentStyle $style,
        public ?CarbonImmutable $dueDate = null,
        public ?CarbonImmutable $validUntil = null,
        public ?int $paymentTermsDays = null,
        public ?string $originalInvoiceNumber = null,
        public ?string $reference = null,
        public ?Discount $documentDiscount = null,
        public ?string $notes = null,
        public ?string $paymentDetails = null,
    ) {}

    /**
     * Build the DTO from data that has already passed DocumentRules validation.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $type = DocumentType::from((string) $data['type']);

        /** @var array<string, mixed> $from */
        $from = $data['from'];
        /** @var array<string, mixed> $to */
        $to = $data['to'];
        /** @var list<array<string, mixed>> $taxRates */
        $taxRates = $data['tax_rates'] ?? [];
        /** @var list<array<string, mixed>> $items */
        $items = $data['items'] ?? [];
        /** @var array<string, mixed> $style */
        $style = $data['style'];
        /** @var array<string, mixed>|null $documentDiscount */
        $documentDiscount = $data['document_discount'] ?? null;

        $paymentTermsDays = $data['payment_terms_days'] ?? null;

        return new self(
            type: $type,
            number: trim((string) $data['number']),
            issueDate: self::date($data['issue_date']) ?? CarbonImmutable::today(),
            currency: strtoupper((string) $data['currency']),
            from: BusinessDetails::fromArray($from),
            to: CustomerDetails::fromArray($to),
            taxRates: array_map(TaxRate::fromArray(...), $taxRates),
            items: array_map(LineItem::fromArray(...), $items),
            style: DocumentStyle::fromArray($style),
            dueDate: $type === DocumentType::Invoice ? self::date($data['due_date'] ?? null) : null,
            validUntil: $type === DocumentType::Quote ? self::date($data['valid_until'] ?? null) : null,
            paymentTermsDays: $paymentTermsDays === null ? null : (int) $paymentTermsDays,
            originalInvoiceNumber: $type === DocumentType::CreditNote ? Text::orNull($data['original_invoice_number'] ?? null) : null,
            reference: Text::orNull($data['reference'] ?? null),
            documentDiscount: Discount::fromArray($documentDiscount),
            notes: Text::orNull($data['notes'] ?? null),
            paymentDetails: Text::orNull($data['payment_details'] ?? null),
        );
    }

    /**
     * Resolve a tax rate by id. Unknown ids (e.g. a rate that was deleted) fall back to "No tax".
     */
    public function taxRate(string $id): TaxRate
    {
        foreach ($this->taxRates as $taxRate) {
            if ($taxRate->id === $id) {
                return $taxRate;
            }
        }

        return TaxRate::none();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'number' => $this->number,
            'issue_date' => $this->issueDate->format('Y-m-d'),
            'due_date' => $this->dueDate?->format('Y-m-d'),
            'valid_until' => $this->validUntil?->format('Y-m-d'),
            'payment_terms_days' => $this->paymentTermsDays,
            'original_invoice_number' => $this->originalInvoiceNumber,
            'reference' => $this->reference,
            'currency' => $this->currency,
            'from' => $this->from->toArray(),
            'to' => $this->to->toArray(),
            'tax_rates' => array_map(fn (TaxRate $rate): array => $rate->toArray(), $this->taxRates),
            'items' => array_map(fn (LineItem $item): array => $item->toArray(), $this->items),
            'document_discount' => $this->documentDiscount?->toArray(),
            'notes' => $this->notes,
            'payment_details' => $this->paymentDetails,
            'style' => $this->style->toArray(),
        ];
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $value) ?: null;
    }
}
