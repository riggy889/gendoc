@include('documents.partials.header')
@include('documents.partials.addresses')
@include('documents.partials.items')

<div class="doc-end">
    @include('documents.partials.totals')

    @if ($document->notes || $document->paymentDetails)
        <div class="doc-columns">
            @include('documents.partials.notes')
            @include('documents.partials.payment-details')
        </div>
    @endif
</div>
