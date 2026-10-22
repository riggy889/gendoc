<?php

namespace App\Documents;

use App\Documents\Data\BusinessDetails;
use App\Documents\Data\DocumentStyle;
use App\Documents\Enums\DateFormat;
use App\Documents\Enums\DocumentType;
use App\Documents\Enums\Font;
use App\Documents\Enums\LogoAlign;
use App\Documents\Enums\Template;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Default payloads (in the browser's array shape) for new documents and their parts.
 */
final class DocumentDefaults
{
    public const PAYMENT_TERMS_DAYS = 30;

    public const QUOTE_VALID_DAYS = 30;

    public const CURRENCY = 'GBP';

    public const TAX_RATE_ID = 'vat';

    /**
     * @return array<string, mixed>
     */
    public static function document(DocumentType $type, CarbonImmutable $today, string $number = ''): array
    {
        return [
            'type' => $type->value,
            'number' => $number,
            'issue_date' => $today->format('Y-m-d'),
            'due_date' => $type === DocumentType::Invoice ? $today->addDays(self::PAYMENT_TERMS_DAYS)->format('Y-m-d') : null,
            'valid_until' => $type === DocumentType::Quote ? $today->addDays(self::QUOTE_VALID_DAYS)->format('Y-m-d') : null,
            'payment_terms_days' => self::PAYMENT_TERMS_DAYS,
            'original_invoice_number' => null,
            'reference' => null,
            'currency' => self::CURRENCY,
            'from' => self::business(),
            'to' => self::customer(),
            'tax_rates' => [self::taxRate(self::TAX_RATE_ID, 'VAT', '20')],
            'items' => [self::item()],
            'document_discount' => null,
            'notes' => null,
            'payment_details' => null,
            'style' => self::style(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function item(string $taxRateId = self::TAX_RATE_ID): array
    {
        return [
            'id' => (string) Str::uuid(),
            'description' => '',
            'quantity' => '1',
            'unit_price_minor' => 0,
            'tax_rate_id' => $taxRateId,
            'discount' => null,
        ];
    }

    /**
     * @return array{id: string, name: string, rate_percent: string}
     */
    public static function taxRate(?string $id = null, string $name = 'Tax', string $ratePercent = '0'): array
    {
        return [
            'id' => $id ?? Str::lower(Str::random(8)),
            'name' => $name,
            'rate_percent' => $ratePercent,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function business(): array
    {
        return [
            'company_name' => null,
            'logo' => null,
            'logo_width' => BusinessDetails::DEFAULT_LOGO_WIDTH,
            'logo_align' => LogoAlign::Left->value,
            ...self::address(),
            'email' => null,
            'phone' => null,
            'website' => null,
            'tax_number' => null,
        ];
    }

    /**
     * @return array<string, null>
     */
    public static function customer(): array
    {
        return [
            'company_name' => null,
            'contact_name' => null,
            ...self::address(),
            'email' => null,
            'tax_number' => null,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function style(): array
    {
        return [
            'template' => Template::Classic->value,
            'accent_color' => DocumentStyle::DEFAULT_ACCENT_COLOR,
            'font' => Font::Inter->value,
            'date_format' => DateFormat::DayMonthYear->value,
        ];
    }

    /**
     * @return array<string, null>
     */
    private static function address(): array
    {
        return [
            'address_line_1' => null,
            'address_line_2' => null,
            'city' => null,
            'region' => null,
            'postcode' => null,
            'country' => null,
        ];
    }
}
