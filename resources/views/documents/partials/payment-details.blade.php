@if ($document->paymentDetails)
    <section class="doc-block">
        <h2 class="doc-label">Payment details</h2>
        <p class="doc-text">{{ $document->paymentDetails }}</p>
    </section>
@endif
