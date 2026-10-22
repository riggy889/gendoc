@use('App\Documents\Enums\DiscountType')
@use('App\Documents\Support\Format')

@php
    $discount = $document->documentDiscount;
    $discountLabel = $discount?->type === DiscountType::Percent ? "Discount ({$discount->value}%)" : 'Discount';
@endphp

<table class="doc-totals">
    <tbody>
        <tr>
            <th scope="row">Subtotal</th>
            <td>{{ Format::money($totals->subtotal) }}</td>
        </tr>
        @if ($totals->hasDocumentDiscount())
            <tr>
                <th scope="row">{{ $discountLabel }}</th>
                <td>&minus;{{ Format::money($totals->documentDiscount) }}</td>
            </tr>
            <tr>
                <th scope="row">Net</th>
                <td>{{ Format::money($totals->net) }}</td>
            </tr>
        @endif
        @foreach ($totals->taxes as $tax)
            <tr>
                <th scope="row">{{ $tax->taxRate->label() }}</th>
                <td>{{ Format::money($tax->tax) }}</td>
            </tr>
        @endforeach
        <tr class="doc-totals__total">
            <th scope="row">{{ $totals->totalLabel }}</th>
            <td>{{ Format::money($totals->total) }}</td>
        </tr>
    </tbody>
</table>
