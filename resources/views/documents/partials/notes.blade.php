@if ($document->notes)
    <section class="doc-block">
        <h2 class="doc-label">Notes</h2>
        <p class="doc-text">{{ $document->notes }}</p>
    </section>
@endif
