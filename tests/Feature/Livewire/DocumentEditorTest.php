<?php

use App\Documents\Data\DocumentTotals;
use App\Livewire\DocumentEditor;
use Carbon\CarbonImmutable;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-01');
});

function editor(): Testable
{
    return Livewire::test(DocumentEditor::class);
}

function editorTotals(Testable $component): DocumentTotals
{
    return $component->viewData('totals');
}

/**
 * @return array<string, mixed>
 */
function firstItem(Testable $component): array
{
    return $component->get('document.items')[0];
}

it('renders the editor on the home page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSeeLivewire(DocumentEditor::class);
});

it('starts with a blank invoice', function () {
    editor()
        ->assertSet('document.type', 'invoice')
        ->assertSet('document.number', 'INV-0001')
        ->assertSet('document.issue_date', '2026-10-01')
        ->assertSet('document.due_date', '2026-10-31')
        ->assertCount('document.items', 1)
        ->assertSee('Amount due');
});

describe('line items', function () {
    it('updates totals when items are added, edited and removed', function () {
        $component = editor();
        $first = firstItem($component)['id'];

        $component->set("prices.{$first}", '10.00')->set('document.items.0.quantity', '2');
        expect(minor(editorTotals($component)->total))->toBe(2400);

        $component->call('addItem');
        $second = $component->get('document.items')[1]['id'];
        $component->set("prices.{$second}", '5.50');
        expect(minor(editorTotals($component)->subtotal))->toBe(2550)
            ->and(minor(editorTotals($component)->total))->toBe(3060);

        $component->call('removeItem', $first);
        expect($component->get('document.items'))->toHaveCount(1)
            ->and(minor(editorTotals($component)->total))->toBe(660);
    });

    it('converts typed prices to minor units', function () {
        $component = editor();
        $id = firstItem($component)['id'];

        $component->set("prices.{$id}", '1,250.5');

        expect(firstItem($component)['unit_price_minor'])->toBe(125050);
    });

    it('shows an error for an invalid price and keeps the previous amount', function () {
        $component = editor();
        $id = firstItem($component)['id'];

        $component->set("prices.{$id}", '12.00')->set("prices.{$id}", '12.345');

        $component->assertHasErrors("prices.{$id}");
        expect(firstItem($component)['unit_price_minor'])->toBe(1200);
    });

    it('duplicates an item directly below the original', function () {
        $component = editor();
        $id = firstItem($component)['id'];
        $component->set('document.items.0.description', 'Design work')->call('duplicateItem', $id);

        $items = $component->get('document.items');
        expect($items)->toHaveCount(2)
            ->and($items[1]['description'])->toBe('Design work')
            ->and($items[1]['id'])->not->toBe($id);
    });

    it('reorders items with the move buttons and drag and drop', function () {
        $component = editor()->call('addItem')->call('addItem');
        [$a, $b, $c] = array_column($component->get('document.items'), 'id');

        $component->call('moveItem', $c, -1);
        expect(array_column($component->get('document.items'), 'id'))->toBe([$a, $c, $b]);

        $component->call('sortItem', $a, 2);
        expect(array_column($component->get('document.items'), 'id'))->toBe([$c, $b, $a]);

        $component->call('moveItem', $c, -1);
        expect(array_column($component->get('document.items'), 'id'))->toBe([$c, $b, $a]);
    });

    it('applies a fixed line discount typed as an amount', function () {
        $component = editor();
        $id = firstItem($component)['id'];

        $component->set("prices.{$id}", '100')
            ->call('setItemDiscountType', $id, 'fixed')
            ->set("lineDiscountAmounts.{$id}", '15');

        expect(firstItem($component)['discount'])->toBe(['type' => 'fixed', 'value' => 1500])
            ->and(minor(editorTotals($component)->subtotal))->toBe(8500);
    });
});

describe('document type', function () {
    it('swaps the date field and labels for each type', function () {
        editor()
            ->assertSee('Due date')
            ->assertDontSee('Valid until')
            ->call('setType', 'quote')
            ->assertSet('document.type', 'quote')
            ->assertSet('document.number', 'QUO-0001')
            ->assertSet('document.valid_until', '2026-10-31')
            ->assertSee('Valid until')
            ->assertDontSee('Due date')
            ->call('setType', 'credit_note')
            ->assertSee('Original invoice number')
            ->assertSee('Total credit')
            ->assertDontSee('Valid until')
            ->assertDontSee('Due date');
    });

    it('ignores unknown types', function () {
        editor()->call('setType', 'receipt')->assertSet('document.type', 'invoice');
    });
});

describe('dates and currency', function () {
    it('sets the due date from the payment terms and issue date', function () {
        editor()
            ->set('document.payment_terms_days', '14')
            ->assertSet('document.payment_terms_days', 14)
            ->assertSet('document.due_date', '2026-10-15')
            ->set('document.issue_date', '2026-11-01')
            ->assertSet('document.due_date', '2026-11-15')
            ->set('document.payment_terms_days', '')
            ->assertSet('document.payment_terms_days', null)
            ->set('document.issue_date', '2026-12-01')
            ->assertSet('document.due_date', '2026-11-15');
    });

    it('keeps typed amounts when the currency changes', function () {
        $component = editor();
        $id = firstItem($component)['id'];

        $component->set("prices.{$id}", '12.50')->set('document.currency', 'JPY');

        expect(firstItem($component)['unit_price_minor'])->toBe(13)
            ->and($component->get("prices.{$id}"))->toBe('13');

        $component->set('document.currency', 'BHD');
        expect(firstItem($component)['unit_price_minor'])->toBe(13000);
    });
});

describe('tax rates and discounts', function () {
    it('moves items to "No tax" when their rate is removed', function () {
        editor()
            ->call('removeTaxRate', 'vat')
            ->assertSet('document.tax_rates', [])
            ->assertSet('document.items.0.tax_rate_id', 'none');
    });

    it('applies a document discount', function () {
        $component = editor();
        $id = firstItem($component)['id'];

        $component->set("prices.{$id}", '200')
            ->call('setDocumentDiscountType', 'percent')
            ->set('document.document_discount.value', '10')
            ->assertSee('Discount (10%)');

        expect(minor(editorTotals($component)->total))->toBe(21600);
    });
});

describe('drafts', function () {
    it('loads a valid draft', function () {
        $payload = documentPayload(['type' => 'quote', 'due_date' => null, 'valid_until' => '2026-10-31']);

        editor()
            ->call('loadDraft', $payload)
            ->assertSet('document.type', 'quote')
            ->assertSet('document.to.company_name', 'Customer Co')
            ->assertSet('dirty', false)
            ->assertSee('Customer Co');
    });

    it('repairs invalid fields in a draft instead of rejecting it', function () {
        $payload = documentPayload([
            'currency' => 'NOPE',
            'style.font' => 'comic-sans',
            'items' => [array_merge(lineItem('lots', 1000), ['description' => 'Kept'])],
        ]);

        $component = editor()->call('loadDraft', $payload);

        $component->assertSet('document.currency', 'GBP')
            ->assertSet('document.style.font', 'inter')
            ->assertSet('document.items.0.quantity', '0')
            ->assertSet('document.items.0.description', 'Kept');
    });

    it('falls back to a blank document when a draft is corrupt', function () {
        editor()
            ->call('loadDraft', ['type' => 'quote', 'from' => 'not an object', 'issue_date' => 12])
            ->assertSet('document.type', 'quote')
            ->assertSet('document.issue_date', '2026-10-01');
    });

    it('never keeps a logo in Livewire state', function () {
        editor()
            ->call('loadDraft', documentPayload(['from.logo' => 'data:image/png;base64,iVBORw0KGgo=']))
            ->assertSet('document.from.logo', null);
    });
});

it('starts a new document, keeping business details and clearing the customer', function () {
    $component = editor();
    $id = firstItem($component)['id'];

    $component->set('document.from.company_name', 'Acme Ltd')
        ->set('document.to.company_name', 'Customer Co')
        ->set("prices.{$id}", '99')
        ->assertSet('dirty', true)
        ->call('newDocument')
        ->assertSet('document.from.company_name', 'Acme Ltd')
        ->assertSet('document.to.company_name', null)
        ->assertSet('dirty', false);

    expect(firstItem($component)['unit_price_minor'])->toBe(0);
});

describe('restoring from the browser', function () {
    it('suggests the next number from stored numbering when there is no draft', function () {
        editor()
            ->call('restore', null, ['invoice' => ['prefix' => 'ACME-', 'highest' => 41]])
            ->assertSet('document.number', 'ACME-0042')
            ->assertSet('numbering.quote', ['prefix' => 'QUO-', 'highest' => 0])
            ->assertSet('dirty', false);
    });

    it('restores a draft and whether it had unsaved changes', function () {
        editor()
            ->call('restore', documentPayload(['number' => 'INV-0007']), [], true)
            ->assertSet('document.number', 'INV-0007')
            ->assertSet('document.to.company_name', 'Customer Co')
            ->assertSet('dirty', true);
    });

    it('repairs junk numbering', function () {
        editor()
            ->call('restore', null, ['invoice' => 'junk', 'quote' => ['prefix' => ['x'], 'highest' => -1]])
            ->assertSet('numbering.invoice', ['prefix' => 'INV-', 'highest' => 0])
            ->assertSet('numbering.quote', ['prefix' => 'QUO-', 'highest' => 0]);
    });
});

describe('numbering', function () {
    it('advances the counter after a download and clears unsaved changes', function () {
        editor()
            ->set('document.notes', 'Changed')
            ->call('recordDownload', 'invoice', 'INV-0001')
            ->assertSet('numbering.invoice.highest', 1)
            ->assertSet('dirty', false)
            ->call('newDocument')
            ->assertSet('document.number', 'INV-0002');
    });

    it('never moves the counter backwards or for custom numbers', function () {
        editor()
            ->call('recordDownload', 'invoice', 'INV-0010')
            ->call('recordDownload', 'invoice', 'INV-0004')
            ->call('recordDownload', 'invoice', '2026/Smith/1')
            ->assertSet('numbering.invoice.highest', 10);
    });

    it('suggests the new type\'s number when switching type, but keeps custom numbers', function () {
        editor()
            ->call('restore', null, ['quote' => ['prefix' => 'QUO-', 'highest' => 4]])
            ->call('setType', 'quote')
            ->assertSet('document.number', 'QUO-0005')
            ->set('document.number', 'Job 12')
            ->call('setType', 'invoice')
            ->assertSet('document.number', 'Job 12');
    });

    it('updates the number as its prefix is edited', function () {
        editor()
            ->set('numbering.invoice.prefix', '2026-')
            ->assertSet('document.number', '2026-0001')
            ->assertSee('Your next new invoice will be 2026-0001.')
            ->set('document.number', 'Custom')
            ->set('numbering.invoice.prefix', 'X-')
            ->assertSet('document.number', 'Custom');
    });
});

describe('workflows', function () {
    it('converts a quote to an invoice', function () {
        $component = editor()
            ->call('restore', documentPayload([
                'type' => 'quote',
                'number' => 'QUO-0003',
                'issue_date' => '2026-09-01',
                'due_date' => null,
                'valid_until' => '2026-10-01',
                'payment_terms_days' => 14,
            ]), ['invoice' => ['prefix' => 'INV-', 'highest' => 8]])
            ->assertSee('Convert to invoice')
            ->call('convertToInvoice')
            ->assertSet('document.type', 'invoice')
            ->assertSet('document.number', 'INV-0009')
            ->assertSet('document.issue_date', '2026-10-01')
            ->assertSet('document.due_date', '2026-10-15')
            ->assertSet('document.valid_until', null)
            ->assertSet('document.to.company_name', 'Customer Co')
            ->assertSet('dirty', true)
            ->assertSee('Create credit note');

        expect(editorTotals($component)->total->getMinorAmount()->toInt())->toBe(12000);
    });

    it('creates a credit note from an invoice', function () {
        editor()
            ->call('restore', documentPayload(['number' => 'INV-0042']), [])
            ->call('createCreditNote')
            ->assertSet('document.type', 'credit_note')
            ->assertSet('document.number', 'CN-0001')
            ->assertSet('document.original_invoice_number', 'INV-0042')
            ->assertSet('document.due_date', null)
            ->assertSet('document.items.0.unit_price_minor', 5000)
            ->assertSee('Credit note for invoice INV-0042');
    });

    it('only converts from the right type', function () {
        editor()
            ->call('convertToInvoice')
            ->assertSet('document.number', 'INV-0001')
            ->call('setType', 'credit_note')
            ->call('createCreditNote')
            ->assertSet('document.type', 'credit_note')
            ->assertSet('document.original_invoice_number', null);
    });

    it('marks the document saved after an export', function () {
        editor()
            ->set('document.notes', 'Changed')
            ->assertSet('dirty', true)
            ->call('markExported')
            ->assertSet('dirty', false);
    });
});
