<?php

use App\Documents\Data\DocumentData;
use App\Documents\Data\TaxRate;
use App\Documents\Enums\DiscountType;
use App\Documents\Enums\DocumentType;
use App\Documents\Enums\Font;
use App\Documents\Enums\LogoAlign;

it('round-trips through toArray and fromArray', function () {
    $payload = documentPayload([
        'items' => [lineItem('2.5', 3333, discount: ['type' => 'percent', 'value' => '12.5'])],
        'document_discount' => ['type' => 'fixed', 'value' => 500],
    ]);

    $document = DocumentData::fromArray($payload);

    expect(DocumentData::fromArray($document->toArray()))->toEqual($document)
        ->and($document->toArray())->toEqual($payload);
});

it('maps the payload onto typed values', function () {
    $document = document([
        'style.font' => 'playfair-display',
        'from.logo_align' => 'right',
        'document_discount' => ['type' => 'percent', 'value' => '10'],
    ]);

    expect($document->type)->toBe(DocumentType::Invoice)
        ->and($document->issueDate->format('Y-m-d'))->toBe('2026-10-01')
        ->and($document->dueDate?->format('Y-m-d'))->toBe('2026-10-31')
        ->and($document->style->font)->toBe(Font::PlayfairDisplay)
        ->and($document->from->logoAlign)->toBe(LogoAlign::Right)
        ->and($document->documentDiscount?->type)->toBe(DiscountType::Percent)
        ->and($document->to->address->lines())->toBe(['2 Low Road', 'Leeds, West Yorkshire', 'LS1 1AA', 'United Kingdom']);
});

it('turns blank text into null so empty fields can be hidden', function () {
    $document = document(['reference' => '   ', 'notes' => '', 'to.email' => ' ', 'from.address_line_2' => '']);

    expect($document->reference)->toBeNull()
        ->and($document->notes)->toBeNull()
        ->and($document->to->email)->toBeNull()
        ->and($document->from->address->line2)->toBeNull();
});

it('keeps only the fields that apply to the document type', function (string $type, array $present, array $absent) {
    $document = document([
        'type' => $type,
        'due_date' => '2026-10-31',
        'valid_until' => '2026-10-31',
        'original_invoice_number' => 'INV-0009',
    ]);

    foreach ($present as $property) {
        expect($document->{$property})->not->toBeNull();
    }
    foreach ($absent as $property) {
        expect($document->{$property})->toBeNull();
    }
})->with([
    ['invoice', ['dueDate'], ['validUntil', 'originalInvoiceNumber']],
    ['quote', ['validUntil'], ['dueDate', 'originalInvoiceNumber']],
    ['credit_note', ['originalInvoiceNumber'], ['dueDate', 'validUntil']],
]);

it('normalises decimal strings', function () {
    $document = document([
        'tax_rates' => [['id' => 'vat', 'name' => 'VAT', 'rate_percent' => '20.000']],
        'items' => [lineItem('02.50', 100, 'vat', ['type' => 'percent', 'value' => '10.0'])],
    ]);

    expect($document->taxRates[0]->ratePercent)->toBe('20')
        ->and($document->taxRates[0]->label())->toBe('VAT 20%')
        ->and($document->items[0]->quantity)->toBe('2.5')
        ->and($document->items[0]->discount?->value)->toBe('10');
});

it('falls back to "No tax" for unknown tax rate ids', function () {
    expect(document()->taxRate('vat20')->name)->toBe('VAT')
        ->and(document()->taxRate('deleted')->id)->toBe(TaxRate::NONE_ID);
});

it('sanitises SVG logos even if validation was bypassed', function () {
    $malicious = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="1" height="1" onclick="alert(2)"/></svg>';
    $document = document(['from.logo' => 'data:image/svg+xml;base64,'.base64_encode($malicious)]);

    $svg = base64_decode(substr((string) $document->from->logo, strlen('data:image/svg+xml;base64,')));

    expect($svg)->toContain('<rect')
        ->not->toContain('script')
        ->not->toContain('onclick');
});

it('drops logos that are not valid data URIs', function () {
    expect(document(['from.logo' => 'https://example.com/logo.png'])->from->logo)->toBeNull();
});

it('labels each document type', function (DocumentType $type, string $title, ?string $dateLabel, string $prefix) {
    expect($type->title())->toBe($title)
        ->and($type->secondaryDateLabel())->toBe($dateLabel)
        ->and($type->defaultNumberPrefix())->toBe($prefix);
})->with([
    [DocumentType::Quote, 'Quote', 'Valid until', 'QUO-'],
    [DocumentType::Invoice, 'Invoice', 'Due date', 'INV-'],
    [DocumentType::CreditNote, 'Credit Note', null, 'CN-'],
]);
