{{-- In the preview the logo lives in Alpine state; in the PDF it is part of the document data. --}}
@php($logoClasses = 'doc-logo doc-logo--'.$document->from->logoAlign->value)

@if ($isPreview)
    <div class="{{ $logoClasses }}" x-show="logo" x-cloak>
        <img wire:ignore x-bind:src="logo" alt="{{ $document->from->companyName ?? 'Logo' }}" style="width: {{ $document->from->logoWidth }}px">
    </div>
@elseif ($document->from->logo)
    <div class="{{ $logoClasses }}">
        <img src="{{ $document->from->logo }}" alt="{{ $document->from->companyName ?? 'Logo' }}" style="width: {{ $document->from->logoWidth }}px">
    </div>
@endif
