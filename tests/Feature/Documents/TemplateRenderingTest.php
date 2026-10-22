<?php

use App\Documents\DocumentCalculator;
use App\Documents\Enums\DocumentType;
use App\Documents\Enums\Template;

/**
 * @return array<string, mixed>
 */
function typeOverrides(DocumentType $type): array
{
    return match ($type) {
        DocumentType::Quote => ['type' => 'quote', 'number' => 'QUO-0001', 'due_date' => null, 'valid_until' => '2026-10-31'],
        DocumentType::Invoice => ['type' => 'invoice'],
        DocumentType::CreditNote => ['type' => 'credit_note', 'number' => 'CN-0001', 'due_date' => null, 'original_invoice_number' => 'INV-0042'],
    };
}

function renderDocument(array $overrides): string
{
    $document = document($overrides);

    return view('documents.render', [
        'document' => $document,
        'totals' => app(DocumentCalculator::class)->calculate($document),
    ])->render();
}

it('renders every template for every document type', function (Template $template, DocumentType $type) {
    $html = renderDocument([...typeOverrides($type), 'style.template' => $template->value]);

    expect($html)
        ->toContain("doc--{$template->value}")
        ->toContain($type->title())
        ->toContain($type->totalLabel())
        ->toContain('Acme Ltd')
        ->toContain('Customer Co')
        ->toContain('£120.00')
        ->toContain('Thanks for your business.')
        ->toContain('Sort code: 00-00-00');

    match ($type) {
        DocumentType::Quote => expect($html)->toContain('Valid until')->not->toContain('Due date'),
        DocumentType::Invoice => expect($html)->toContain('Due date')->not->toContain('Valid until'),
        DocumentType::CreditNote => expect($html)->toContain('Credit note for invoice INV-0042')->not->toContain('Due date'),
    };
})->with(Template::cases())->with(DocumentType::cases());

it('applies the accent colour and font through CSS variables', function () {
    $html = renderDocument(['style.accent_color' => '#fde047', 'style.font' => 'playfair-display']);

    expect($html)
        ->toContain('--accent: #fde047;')
        ->toContain('--accent-ink: #111827;')
        ->toContain('--doc-font: &#039;Playfair Display Variable&#039;, Georgia, serif;');
});

it('formats dates in the chosen format', function (string $format, string $expected) {
    expect(renderDocument(['style.date_format' => $format]))->toContain($expected);
})->with([
    ['d/m/Y', '01/10/2026'],
    ['m/d/Y', '10/01/2026'],
    ['j M Y', '1 Oct 2026'],
]);

it('hides blank optional fields rather than rendering empty labels', function () {
    $html = renderDocument(['reference' => null, 'notes' => null, 'payment_details' => null, 'to.email' => null]);

    expect($html)
        ->not->toContain('Reference')
        ->not->toContain('Notes')
        ->not->toContain('Payment details')
        ->not->toContain('sam@customer.test');
});

it('escapes user text', function () {
    expect(renderDocument(['from.company_name' => '<script>alert(1)</script>']))
        ->not->toContain('<script>alert(1)</script>')
        ->toContain('&lt;script&gt;');
});
