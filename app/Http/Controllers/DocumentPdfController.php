<?php

namespace App\Http\Controllers;

use App\Documents\Data\DocumentData;
use App\Documents\DocumentCalculator;
use App\Http\Requests\GenerateDocumentPdfRequest;
use App\Pdf\PdfRenderer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class DocumentPdfController extends Controller
{
    public function __invoke(GenerateDocumentPdfRequest $request, DocumentCalculator $calculator, PdfRenderer $renderer): Response
    {
        $document = DocumentData::fromArray($request->validated());

        try {
            $response = $renderer
                ->render($document, $calculator->calculate($document), $this->filename($document))
                ->toResponse($request);
        } catch (Throwable $exception) {
            // Log what went wrong but never the document itself.
            Log::error('PDF generation failed', ['exception' => $exception::class, 'message' => $exception->getMessage()]);

            return response()->json(['message' => "Sorry, we couldn't create your PDF. Please try again in a moment."], 503);
        }

        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    /**
     * "{type}-{number}.pdf", reduced to characters that are safe in a filename on every platform.
     */
    private function filename(DocumentData $document): string
    {
        $type = str_replace('_', '-', $document->type->value);
        $number = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', Str::ascii($document->number)), '-.');

        return ($number === '' ? $type : "{$type}-{$number}").'.pdf';
    }
}
