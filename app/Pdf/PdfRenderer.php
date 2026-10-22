<?php

namespace App\Pdf;

use App\Documents\Data\DocumentData;
use App\Documents\Data\DocumentTotals;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * Renders a document to PDF from the same view as the live preview.
 *
 * Everything the page needs (styles, the font and the logo) is inlined into the HTML, because the
 * renderer is not allowed to fetch anything over the network.
 */
final class PdfRenderer
{
    public function __construct(private readonly EmbeddedFonts $fonts) {}

    public function render(DocumentData $document, DocumentTotals $totals, string $filename): PdfBuilder
    {
        return Pdf::view('documents.pdf', [
            'document' => $document,
            'totals' => $totals,
            'styles' => $this->fonts->css($document->style->font)."\n".$this->documentCss(),
        ])
            ->format(Format::A4)
            ->margins(15, 15, 15, 15)
            ->download($filename);
    }

    private function documentCss(): string
    {
        return (string) file_get_contents(resource_path('css/document.css'));
    }
}
