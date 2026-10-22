@use('App\Documents\Enums\DocumentType')
@use('App\Documents\Support\Format')

@php
    $dateFormat = $document->style->dateFormat;
    $meta = array_filter([
        $document->type->title().' number' => $document->number,
        'Issue date' => Format::date($document->issueDate, $dateFormat),
        'Due date' => Format::date($document->dueDate, $dateFormat),
        'Valid until' => Format::date($document->validUntil, $dateFormat),
        'Payment terms' => $document->type === DocumentType::Quote && $document->paymentTermsDays ? "{$document->paymentTermsDays} days" : null,
        'Reference' => $document->reference,
    ], fn ($value) => $value !== null && $value !== '');
@endphp

<header class="doc-header">
    @include('documents.partials.logo')

    <div class="doc-header__row">
        <div>
            <h1 class="doc-title">{{ $document->type->title() }}</h1>
            @if ($document->type === DocumentType::CreditNote && $document->originalInvoiceNumber)
                <p class="doc-subtitle">Credit note for invoice {{ $document->originalInvoiceNumber }}</p>
            @endif
        </div>

        <dl class="doc-meta">
            @foreach ($meta as $label => $value)
                <div class="doc-meta__row">
                    <dt>{{ $label }}</dt>
                    <dd>{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</header>
