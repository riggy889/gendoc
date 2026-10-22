<?php

use App\Documents\Data\DocumentData;
use Brick\Money\Money;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

/**
 * A complete, valid document payload in the shape the browser sends. Overrides use dot
 * notation (e.g. ['style.font' => 'lato']) and replace lists wholesale.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function documentPayload(array $overrides = []): array
{
    $payload = [
        'type' => 'invoice',
        'number' => 'INV-0001',
        'issue_date' => '2026-10-01',
        'due_date' => '2026-10-31',
        'valid_until' => null,
        'payment_terms_days' => 30,
        'original_invoice_number' => null,
        'reference' => 'PO-123',
        'currency' => 'GBP',
        'from' => [
            'company_name' => 'Acme Ltd',
            'logo' => null,
            'logo_width' => 120,
            'logo_align' => 'left',
            'address_line_1' => '1 High Street',
            'address_line_2' => null,
            'city' => 'London',
            'region' => null,
            'postcode' => 'SW1A 1AA',
            'country' => 'United Kingdom',
            'email' => 'billing@acme.test',
            'phone' => '020 7946 0000',
            'website' => 'acme.test',
            'tax_number' => 'GB123456789',
        ],
        'to' => [
            'company_name' => 'Customer Co',
            'contact_name' => 'Sam Smith',
            'address_line_1' => '2 Low Road',
            'address_line_2' => null,
            'city' => 'Leeds',
            'region' => 'West Yorkshire',
            'postcode' => 'LS1 1AA',
            'country' => 'United Kingdom',
            'email' => 'sam@customer.test',
            'tax_number' => null,
        ],
        'tax_rates' => [
            ['id' => 'vat20', 'name' => 'VAT', 'rate_percent' => '20'],
        ],
        'items' => [
            lineItem('2', 5000),
        ],
        'document_discount' => null,
        'notes' => 'Thanks for your business.',
        'payment_details' => "Sort code: 00-00-00\nAccount: 12345678",
        'style' => [
            'template' => 'classic',
            'accent_color' => '#2563eb',
            'font' => 'inter',
            'date_format' => 'd/m/Y',
        ],
    ];

    foreach ($overrides as $key => $value) {
        Arr::set($payload, $key, $value);
    }

    return $payload;
}

/**
 * @param  array{type: string, value: string|int}|null  $discount
 * @return array<string, mixed>
 */
function lineItem(string $quantity, int $unitPriceMinor, string $taxRateId = 'vat20', ?array $discount = null, string $description = 'Work'): array
{
    return [
        'id' => (string) Str::uuid(),
        'description' => $description,
        'quantity' => $quantity,
        'unit_price_minor' => $unitPriceMinor,
        'tax_rate_id' => $taxRateId,
        'discount' => $discount,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 */
function document(array $overrides = []): DocumentData
{
    return DocumentData::fromArray(documentPayload($overrides));
}

function minor(Money $money): int
{
    return $money->getMinorAmount()->toInt();
}
