<x-editor.section id="document" title="Document">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-form.input label="{{ $type->title() }} number" model="document.number" />
        <x-form.input label="Reference (optional)" model="document.reference" hint="For example, a PO number." />
        <x-form.input label="Issue date" model="document.issue_date" type="date" :live="false" />

        @if ($type !== \App\Documents\Enums\DocumentType::CreditNote)
            <x-form.select label="Payment terms" model="document.payment_terms_days">
                <option value="7">7 days</option>
                <option value="14">14 days</option>
                <option value="30">30 days</option>
                <option value="60">60 days</option>
                <option value="">Custom</option>
            </x-form.select>
        @endif

        @if ($type === \App\Documents\Enums\DocumentType::Invoice)
            <x-form.input label="Due date" model="document.due_date" type="date" :live="false"
                :hint="$document['payment_terms_days'] ? 'Set from the payment terms. Choose Custom to pick a date.' : null"
                :readonly="(bool) $document['payment_terms_days']" />
        @elseif ($type === \App\Documents\Enums\DocumentType::Quote)
            <x-form.input label="Valid until" model="document.valid_until" type="date" :live="false" />
        @else
            <x-form.input label="Original invoice number" model="document.original_invoice_number" />
        @endif

        <x-form.select label="Currency" model="document.currency" class="sm:col-span-2">
            <optgroup label="Common currencies">
                @foreach ($currencies['common'] as $code => $label)
                    <option value="{{ $code }}">{{ $label }}</option>
                @endforeach
            </optgroup>
            <optgroup label="All currencies">
                @foreach ($currencies['other'] as $code => $label)
                    <option value="{{ $code }}">{{ $label }}</option>
                @endforeach
            </optgroup>
        </x-form.select>
    </div>
</x-editor.section>
