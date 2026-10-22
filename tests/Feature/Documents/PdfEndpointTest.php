<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

function postPdf(array $payload): TestResponse
{
    return test()->postJson(route('documents.pdf'), $payload);
}

/**
 * The HTML from a request to Gotenberg, which arrives as the multipart "files" part.
 */
function gotenbergHtml(Request $request): string
{
    return collect($request->data())->firstWhere('name', 'files')['contents'] ?? '';
}

describe('with the PDF fake', function () {
    beforeEach(fn () => Pdf::fake());

    it('downloads the document as an A4 PDF', function () {
        postPdf(documentPayload())->assertOk();

        Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->isDownload()
            && $pdf->downloadName === 'invoice-INV-0001.pdf'
            && $pdf->format === 'a4'
            && $pdf->viewName === 'documents.pdf'
            && $pdf->contains(['Invoice', 'Acme Ltd', 'Customer Co', '£120.00']));
    });

    it('names the file after the type and a filename-safe number', function (array $overrides, string $expected) {
        postPdf(documentPayload($overrides))->assertOk();

        Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->downloadName === $expected);
    })->with([
        'credit note' => [['type' => 'credit_note', 'number' => 'CN-0003', 'due_date' => null, 'original_invoice_number' => 'INV-0001'], 'credit-note-CN-0003.pdf'],
        'slashes and spaces' => [['number' => '2026/Smith 1'], 'invoice-2026-Smith-1.pdf'],
        'path traversal' => [['number' => '../../etc/passwd'], 'invoice-etc-passwd.pdf'],
        'quotes and accents' => [['number' => 'Café "No. 5"'], 'invoice-Cafe-No.-5.pdf'],
        'nothing usable' => [['number' => '///'], 'invoice.pdf'],
    ]);

    it('inlines only the selected font, with no external references', function () {
        postPdf(documentPayload(['style.font' => 'merriweather']))->assertOk();

        Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) {
            $html = $pdf->getHtml();

            return str_contains($html, "font-family: 'Merriweather Variable'")
                && str_contains($html, 'url(data:font/woff2;base64,')
                && ! str_contains($html, "font-family: 'Inter Variable'")
                && ! preg_match('#url\((?!data:)|<link|<script|https?://(?!www\.w3\.org)#', $html);
        });
    });

    it('includes the logo as an image, never inline SVG', function () {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>';

        postPdf(documentPayload(['from.logo' => 'data:image/svg+xml;base64,'.base64_encode($svg)]))->assertOk();

        Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => str_contains($pdf->getHtml(), '<img src="data:image/svg+xml;base64,')
            && ! str_contains($pdf->getHtml(), '<svg'));
    });

    it('rejects invalid documents with readable errors', function () {
        postPdf(documentPayload(['number' => '', 'items' => [lineItem('abc', 100)]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'number' => 'The document number field is required.',
                'items.0.quantity' => 'The item 1 quantity field must have 0-4 decimal places.',
            ]);
    });

    it('rejects a malicious SVG logo', function () {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';

        postPdf(documentPayload(['from.logo' => 'data:image/svg+xml;base64,'.base64_encode($svg)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('from.logo');
    });

    it('allows 10 PDFs a minute per IP, then explains the limit', function () {
        foreach (range(1, 10) as $attempt) {
            postPdf(documentPayload())->assertOk();
        }

        postPdf(documentPayload())
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertJsonPath('message', "You've created a lot of PDFs in the last minute. Please wait a moment and try again.");

        test()->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->postJson(route('documents.pdf'), documentPayload())
            ->assertOk();
    });
});

describe('with the Gotenberg driver', function () {
    beforeEach(fn () => config(['laravel-pdf.driver' => 'gotenberg', 'laravel-pdf.gotenberg.url' => 'http://gotenberg.test']));

    it('streams the PDF from Gotenberg as a download', function () {
        Http::fake(['gotenberg.test/*' => Http::response('%PDF-1.7 fake', 200)]);

        postPdf(documentPayload(['number' => 'INV-0042']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="invoice-INV-0042.pdf"')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertContent('%PDF-1.7 fake');

        Http::assertSent(fn (Request $request) => $request->url() === 'http://gotenberg.test/forms/chromium/convert/html'
            && str_contains(gotenbergHtml($request), 'Acme Ltd'));
    });

    it('shows a friendly error and logs nothing from the document when rendering fails', function () {
        Http::fake(['gotenberg.test/*' => Http::response('Chromium crashed', 500)]);
        Log::spy();

        postPdf(documentPayload(['from.company_name' => 'Secret Client Ltd']))
            ->assertServiceUnavailable()
            ->assertJsonPath('message', "Sorry, we couldn't create your PDF. Please try again in a moment.");

        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message, array $context) => $message === 'PDF generation failed'
            && ! str_contains(json_encode($context), 'Secret Client'));
    });
});
