@include('documents.partials.header')
@include('documents.partials.addresses')
@include('documents.partials.items')

<div class="doc-end">
    @include('documents.partials.totals')
    @include('documents.partials.notes')
    @include('documents.partials.payment-details')
</div>
