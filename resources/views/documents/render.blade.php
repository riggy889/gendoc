{{--
    The single view used for both the live preview and the PDF.

    @var \App\Documents\Data\DocumentData $document
    @var \App\Documents\Data\DocumentTotals $totals
    @var bool $isPreview  true in the editor, where the logo comes from Alpine state
--}}
@php
    $isPreview ??= false;
    $template = 'documents.templates.'.$document->style->template->value;
    $template = view()->exists($template) ? $template : 'documents.templates.classic';
@endphp

<article class="doc doc--{{ $document->style->template->value }}" style="{{ $document->style->cssVariables() }}">
    @include($template)
</article>
