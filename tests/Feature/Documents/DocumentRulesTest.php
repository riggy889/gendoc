<?php

use App\Documents\Logo\LogoDataUri;
use App\Documents\Validation\DocumentRules;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;

/**
 * @param  array<string, mixed>  $overrides
 */
function validationErrors(array $overrides = []): MessageBag
{
    return Validator::make(documentPayload($overrides), DocumentRules::rules())->errors();
}

function dataUri(string $type, string $bytes): string
{
    return "data:{$type};base64,".base64_encode($bytes);
}

function png(int $paddingBytes = 0): string
{
    $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');

    return dataUri('image/png', $pixel.str_repeat("\0", $paddingBytes));
}

function svg(string $body = '<rect width="10" height="10" fill="#f00"/>'): string
{
    return dataUri('image/svg+xml', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">'.$body.'</svg>');
}

describe('valid payloads', function () {
    it('accepts a complete document of each type', function (array $overrides) {
        expect(validationErrors($overrides)->all())->toBe([]);
    })->with([
        'invoice' => [[]],
        'quote' => [['type' => 'quote', 'due_date' => null, 'valid_until' => '2026-10-31']],
        'credit note' => [['type' => 'credit_note', 'due_date' => null, 'original_invoice_number' => 'INV-0001']],
    ]);

    it('accepts a minimal document with optional fields left out or blank', function () {
        expect(validationErrors([
            'reference' => null,
            'payment_terms_days' => null,
            'from' => ['logo_width' => 120, 'logo_align' => 'right', 'company_name' => ''],
            'to' => [],
            'tax_rates' => [],
            'items' => [],
            'notes' => null,
            'payment_details' => '',
        ])->all())->toBe([]);
    });

    it('accepts discounts, multiple rates and decimal quantities', function () {
        expect(validationErrors([
            'tax_rates' => [
                ['id' => 'vat20', 'name' => 'VAT', 'rate_percent' => '20'],
                ['id' => 'ny', 'name' => 'Sales tax', 'rate_percent' => '8.875'],
            ],
            'items' => [
                lineItem('2.5', 3333, discount: ['type' => 'percent', 'value' => '12.5']),
                lineItem('0.0001', 1, 'ny', ['type' => 'fixed', 'value' => 100]),
                lineItem('1', 0, 'none'),
            ],
            'document_discount' => ['type' => 'percent', 'value' => 10],
        ])->all())->toBe([]);
    });

    it('accepts exactly the maximum number of items', function () {
        $items = array_map(fn () => lineItem('1', 100), range(1, DocumentRules::MAX_ITEMS));

        expect(validationErrors(['items' => $items])->all())->toBe([]);
    });
});

describe('document fields', function () {
    it('requires the type-specific fields', function (array $overrides, string $field) {
        expect(validationErrors($overrides)->keys())->toContain($field);
    })->with([
        'invoice without a due date' => [['due_date' => null], 'due_date'],
        'quote without a valid-until date' => [['type' => 'quote', 'valid_until' => null], 'valid_until'],
        'credit note without the original invoice' => [['type' => 'credit_note', 'original_invoice_number' => null], 'original_invoice_number'],
    ]);

    it('rejects invalid values', function (array $overrides, string $field) {
        expect(validationErrors($overrides)->keys())->toContain($field);
    })->with([
        'unknown type' => [['type' => 'receipt'], 'type'],
        'missing number' => [['number' => ''], 'number'],
        'number too long' => [['number' => str_repeat('1', 51)], 'number'],
        'badly formatted date' => [['issue_date' => '01/10/2026'], 'issue_date'],
        'due date before issue date' => [['due_date' => '2026-09-30'], 'due_date'],
        'unsupported payment terms' => [['payment_terms_days' => 15], 'payment_terms_days'],
        'reference too long' => [['reference' => str_repeat('a', 101)], 'reference'],
        'notes too long' => [['notes' => str_repeat('a', 5001)], 'notes'],
        'company name too long' => [['from.company_name' => str_repeat('a', 151)], 'from.company_name'],
        'invalid customer email' => [['to.email' => 'not-an-email'], 'to.email'],
        'logo too narrow' => [['from.logo_width' => 39], 'from.logo_width'],
        'unknown logo alignment' => [['from.logo_align' => 'centre'], 'from.logo_align'],
    ]);

    it('rejects invalid currency codes', function (mixed $currency) {
        expect(validationErrors(['currency' => $currency])->keys())->toContain('currency');
    })->with([
        'not a currency' => ['XYZ'],
        'withdrawn currency' => ['DEM'],
        'lowercase' => ['gbp'],
        'too short' => ['GB'],
        'not a string' => [826],
        'missing' => [null],
    ]);

    it('accepts any ISO 4217 currency', function (string $currency) {
        expect(validationErrors(['currency' => $currency])->all())->toBe([]);
    })->with(['GBP', 'EUR', 'USD', 'JPY', 'KRW', 'BHD', 'CHF']);
});

describe('style', function () {
    it('rejects values outside the whitelists', function (string $field, string $value) {
        expect(validationErrors([$field => $value])->keys())->toContain($field);
    })->with([
        ['style.template', 'fancy'],
        ['style.font', 'comic-sans'],
        ['style.date_format', 'Y-m-d'],
        ['style.accent_color', 'red'],
        ['style.accent_color', '#fff'],
        ['style.accent_color', '#12345g'],
        ['style.accent_color', '#123456; background: url(x)'],
    ]);
});

describe('tax rates', function () {
    it('rejects invalid tax rates', function (array $taxRates, string $field) {
        expect(validationErrors(['tax_rates' => $taxRates])->keys())->toContain($field);
    })->with([
        'reserved "none" id' => [[['id' => 'none', 'name' => 'Nothing', 'rate_percent' => '0']], 'tax_rates.0.id'],
        'duplicate ids' => [[['id' => 'a', 'name' => 'A', 'rate_percent' => '1'], ['id' => 'a', 'name' => 'B', 'rate_percent' => '2']], 'tax_rates.1.id'],
        'four decimal places' => [[['id' => 'a', 'name' => 'A', 'rate_percent' => '8.8755']], 'tax_rates.0.rate_percent'],
        'over 100%' => [[['id' => 'a', 'name' => 'A', 'rate_percent' => '100.5']], 'tax_rates.0.rate_percent'],
        'negative' => [[['id' => 'a', 'name' => 'A', 'rate_percent' => '-5']], 'tax_rates.0.rate_percent'],
        'missing name' => [[['id' => 'a', 'name' => '', 'rate_percent' => '5']], 'tax_rates.0.name'],
    ]);
});

describe('items', function () {
    it('rejects more than the maximum number of items', function () {
        $items = array_map(fn () => lineItem('1', 100), range(1, DocumentRules::MAX_ITEMS + 1));

        expect(validationErrors(['items' => $items])->keys())->toContain('items');
    });

    it('rejects invalid item fields', function (string $field, mixed $value) {
        $item = array_merge(lineItem('1', 100), [$field => $value]);

        expect(validationErrors(['items' => [$item]])->keys())->toContain("items.0.{$field}");
    })->with([
        ['id', 'not-a-uuid'],
        ['quantity', '1.23456'],
        ['quantity', '-1'],
        ['quantity', '1000001'],
        ['quantity', 'two'],
        ['unit_price_minor', 12.5],
        ['unit_price_minor', -1],
        ['unit_price_minor', DocumentRules::MAX_MINOR_AMOUNT + 1],
        ['description', str_repeat('a', 2001)],
        ['tax_rate_id', ''],
    ]);

    it('rejects invalid discounts', function (mixed $discount) {
        $errors = validationErrors([
            'items' => [array_merge(lineItem('1', 100), ['discount' => $discount])],
            'document_discount' => $discount,
        ]);

        expect($errors->keys())->toContain('items.0.discount', 'document_discount');
    })->with([
        'percent over 100' => [['type' => 'percent', 'value' => '100.01']],
        'percent with three decimals' => [['type' => 'percent', 'value' => '1.005']],
        'negative percent' => [['type' => 'percent', 'value' => '-5']],
        'fixed as a float' => [['type' => 'fixed', 'value' => 5.5]],
        'negative fixed' => [['type' => 'fixed', 'value' => -100]],
        'unknown type' => [['type' => 'bogo', 'value' => 1]],
        'extra keys' => [['type' => 'fixed', 'value' => 1, 'extra' => true]],
        'not an array' => ['10%'],
    ]);

    it('rejects unknown item keys', function () {
        $item = array_merge(lineItem('1', 100), ['price_float' => 1.0]);

        expect(validationErrors(['items' => [$item]])->keys())->toContain('items.0');
    });
});

describe('logo', function () {
    it('accepts each allowed image type', function (string $logo) {
        expect(validationErrors(['from.logo' => $logo])->all())->toBe([]);
    })->with([
        'png' => [png()],
        'jpeg' => [dataUri('image/jpeg', "\xFF\xD8\xFF\xE0".'jpeg-body')],
        'webp' => [dataUri('image/webp', 'RIFF'.pack('V', 4).'WEBPVP8 ')],
        'svg' => [svg()],
    ]);

    it('accepts a logo of exactly 2 MB', function () {
        $logo = png(LogoDataUri::MAX_BYTES - strlen(base64_decode(substr(png(), 22))));

        expect(validationErrors(['from.logo' => $logo])->all())->toBe([]);
    });

    it('rejects a logo over 2 MB once decoded', function () {
        // Just over the limit, so the friendly size message is shown rather than the raw length guard.
        $errors = validationErrors(['from.logo' => png(LogoDataUri::MAX_BYTES)]);

        expect($errors->first('from.logo'))->toBe('The logo must be 2 MB or smaller.');
    });

    it('rejects logos that are not allowed images', function (string $logo) {
        expect(validationErrors(['from.logo' => $logo])->keys())->toContain('from.logo');
    })->with([
        'gif' => [dataUri('image/gif', 'GIF89a')],
        'html' => [dataUri('text/html', '<script>alert(1)</script>')],
        'remote url' => ['https://example.com/logo.png'],
        'not base64' => ['data:image/png;base64,***'],
        'url-encoded svg' => ['data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg"/>'],
        'png header that is really a jpeg' => [dataUri('image/png', "\xFF\xD8\xFF\xE0")],
        'svg that is not xml' => [dataUri('image/svg+xml', 'not svg')],
    ]);

    it('rejects malicious SVGs', function (string $body) {
        $errors = validationErrors(['from.logo' => svg($body)]);

        expect($errors->first('from.logo'))->toContain('scripts, event handlers or external references');
    })->with([
        'script element' => ['<script>alert(document.cookie)</script>'],
        'event handler' => ['<rect width="10" height="10" onload="alert(1)"/>'],
        'remote image' => ['<image href="https://evil.test/track.png" width="10" height="10"/>'],
        'javascript link' => ['<a href="javascript:alert(1)"><rect width="10" height="10"/></a>'],
        'foreign object' => ['<foreignObject><iframe src="https://evil.test"></iframe></foreignObject>'],
    ]);
});
