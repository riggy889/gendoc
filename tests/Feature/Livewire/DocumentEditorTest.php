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
