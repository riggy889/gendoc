@php
    $from = $document->from;
    $to = $document->to;
@endphp

<section class="doc-addresses">
    @include('documents.partials.party', [
        'heading' => 'From',
        'emphasiseFirst' => true,
        'lines' => [
            $from->companyName,
            ...$from->address->lines(),
            $from->email,
            $from->phone,
            $from->website,
            $from->taxNumber ? "Tax number: {$from->taxNumber}" : null,
        ],
    ])

    @include('documents.partials.party', [
        'heading' => $document->type->recipientLabel(),
        'emphasiseFirst' => true,
        'lines' => [
            $to->companyName,
            $to->contactName,
            ...$to->address->lines(),
            $to->email,
            $to->taxNumber ? "Tax number: {$to->taxNumber}" : null,
        ],
    ])
</section>
