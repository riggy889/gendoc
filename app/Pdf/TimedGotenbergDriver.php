<?php

namespace App\Pdf;

use Illuminate\Http\Client\PendingRequest;
use Spatie\LaravelPdf\Drivers\GotenbergDriver;
use Spatie\LaravelPdf\PdfOptions;

/**
 * The package's Gotenberg driver with a hard HTTP timeout, so a stuck render fails fast instead of
 * holding a PHP worker. The package only sets a timeout when waiting for JavaScript readiness.
 */
class TimedGotenbergDriver extends GotenbergDriver
{
    private int $timeout;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        $this->timeout = (int) ($config['timeout'] ?? 20);
    }

    protected function buildRequest(string $html, ?string $headerHtml, ?string $footerHtml, PdfOptions $options): PendingRequest
    {
        return parent::buildRequest($html, $headerHtml, $footerHtml, $options)->timeout($this->timeout);
    }
}
