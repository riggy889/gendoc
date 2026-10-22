@php($discountType = $document['document_discount']['type'] ?? '')

<x-editor.section id="tax" title="Tax & discounts">
    <fieldset class="space-y-3">
        <legend class="label">Tax rates</legend>
        @foreach ($document['tax_rates'] as $index => $rate)
            <div wire:key="tax-{{ $rate['id'] }}" class="flex items-start gap-2">
                <x-form.input label="Name" model="document.tax_rates.{{ $index }}.name" class="flex-1" />
                <x-form.input label="Rate" model="document.tax_rates.{{ $index }}.rate_percent" inputmode="decimal" prefix="%" class="w-28" />
                <button type="button" class="icon-btn mt-7 hover:text-red-700" wire:click="removeTaxRate('{{ $rate['id'] }}')" aria-label="Remove tax rate {{ $rate['name'] }}">
                    <x-icon name="trash" />
                </button>
            </div>
        @endforeach
        <p class="hint">"No tax (0%)" is always available. Items using a removed rate switch to it.</p>
        <button type="button" class="btn" wire:click="addTaxRate" @disabled(count($document['tax_rates']) >= \App\Documents\Validation\DocumentRules::MAX_TAX_RATES)>
            <x-icon name="plus" />
            Add tax rate
        </button>
    </fieldset>

    <div class="grid gap-4 border-t border-slate-200 pt-4 sm:grid-cols-2">
        <div>
            <label for="document-discount-type" class="label">Discount on the whole document</label>
            <select id="document-discount-type" class="input" wire:change="setDocumentDiscountType($event.target.value)">
                <option value="" @selected($discountType === '')>None</option>
                <option value="percent" @selected($discountType === 'percent')>Percentage</option>
                <option value="fixed" @selected($discountType === 'fixed')>Fixed amount</option>
            </select>
            <p class="hint">Applied after line discounts, before tax.</p>
        </div>
        @if ($discountType === 'percent')
            <x-form.input label="Discount %" model="document.document_discount.value" inputmode="decimal" prefix="%" error="document.document_discount" />
        @elseif ($discountType === 'fixed')
            <x-form.input label="Discount amount" model="documentDiscountAmount" inputmode="decimal" :prefix="$currencySymbol"
                :error="$errors->has('documentDiscountAmount') ? 'documentDiscountAmount' : 'document.document_discount'" />
        @endif
    </div>
</x-editor.section>
