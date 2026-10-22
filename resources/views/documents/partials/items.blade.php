@use('App\Documents\Enums\DiscountType')
@use('App\Documents\Support\Format')
@use('Brick\Money\Money')

@php
    $showDiscount = $totals->hasLineDiscounts();
    $showTax = $totals->taxes !== [];
@endphp

<table class="doc-items">
    <thead>
        <tr>
            <th scope="col" class="doc-items__description">Description</th>
            <th scope="col" class="doc-num">Qty</th>
            <th scope="col" class="doc-num">Unit price</th>
            @if ($showDiscount)
                <th scope="col" class="doc-num">Discount</th>
            @endif
            @if ($showTax)
                <th scope="col" class="doc-num">Tax</th>
            @endif
            <th scope="col" class="doc-num">Amount</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($document->items as $item)
            @php $line = $totals->line($item->id); @endphp
            <tr>
                <td class="doc-items__description">{{ $item->description }}</td>
                <td class="doc-num">{{ Format::decimal($item->quantity) }}</td>
                <td class="doc-num">{{ Format::money(Money::ofMinor($item->unitPriceMinor, $document->currency)) }}</td>
                @if ($showDiscount)
                    <td class="doc-num">
                        @if ($line->discount->isZero())
                            &ndash;
                        @elseif ($item->discount?->type === DiscountType::Percent)
                            {{ $item->discount->value }}%
                        @else
                            &minus;{{ Format::money($line->discount) }}
                        @endif
                    </td>
                @endif
                @if ($showTax)
                    <td class="doc-num">{{ $line->taxRate->isNone() ? '–' : $line->taxRate->ratePercent.'%' }}</td>
                @endif
                <td class="doc-num">{{ Format::money($line->net) }}</td>
            </tr>
        @empty
            <tr>
                <td class="doc-items__empty" colspan="{{ 4 + (int) $showDiscount + (int) $showTax }}">No items</td>
            </tr>
        @endforelse
    </tbody>
</table>
