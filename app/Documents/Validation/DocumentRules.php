<?php

namespace App\Documents\Validation;

use App\Documents\Enums\DateFormat;
use App\Documents\Enums\DocumentType;
use App\Documents\Enums\Font;
use App\Documents\Enums\LogoAlign;
use App\Documents\Enums\Template;
use App\Documents\Logo\LogoDataUri;
use App\Documents\Validation\Rules\Iso4217Currency;
use App\Documents\Validation\Rules\LogoImage;
use App\Documents\Validation\Rules\ValidDiscount;
use Illuminate\Validation\Rule;

/**
 * The validation rules for a full document payload, shared by the PDF request and JSON import.
 */
final class DocumentRules
{
    public const MAX_ITEMS = 200;

    public const MAX_TAX_RATES = 20;

    /** The largest amount in minor units accepted for a price or fixed discount (100 million in a 2-decimal currency). */
    public const MAX_MINOR_AMOUNT = 10_000_000_000;

    public const MAX_QUANTITY = 1_000_000;

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(DocumentType::class)],
            'number' => ['required', 'string', 'max:50'],
            'issue_date' => ['required', 'date_format:Y-m-d'],
            'due_date' => ['nullable', 'required_if:type,invoice', 'date_format:Y-m-d', 'after_or_equal:issue_date'],
            'valid_until' => ['nullable', 'required_if:type,quote', 'date_format:Y-m-d', 'after_or_equal:issue_date'],
            'payment_terms_days' => ['nullable', 'integer', Rule::in([7, 14, 30, 60])],
            'original_invoice_number' => ['nullable', 'required_if:type,credit_note', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:100'],
            'currency' => ['required', new Iso4217Currency],

            'from' => ['present', 'array'],
            ...self::partyRules('from'),
            'from.logo' => ['nullable', 'string', 'max:'.(LogoDataUri::MAX_BYTES * 2), new LogoImage],
            'from.logo_width' => ['required', 'integer', 'between:40,240'],
            'from.logo_align' => ['required', Rule::enum(LogoAlign::class)],
            'from.phone' => ['nullable', 'string', 'max:50'],
            'from.website' => ['nullable', 'string', 'max:150'],

            'to' => ['present', 'array'],
            ...self::partyRules('to'),
            'to.contact_name' => ['nullable', 'string', 'max:150'],

            'tax_rates' => ['present', 'array', 'max:'.self::MAX_TAX_RATES],
            'tax_rates.*' => ['array:id,name,rate_percent'],
            'tax_rates.*.id' => ['required', 'string', 'max:36', 'distinct', Rule::notIn(['none'])],
            'tax_rates.*.name' => ['required', 'string', 'max:50'],
            'tax_rates.*.rate_percent' => ['required', 'decimal:0,3', 'between:0,100'],

            'items' => ['present', 'array', 'max:'.self::MAX_ITEMS],
            'items.*' => ['array:id,description,quantity,unit_price_minor,tax_rate_id,discount'],
            'items.*.id' => ['required', 'uuid', 'distinct'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.quantity' => ['required', 'decimal:0,4', 'between:0,'.self::MAX_QUANTITY],
            'items.*.unit_price_minor' => ['required', 'integer', 'between:0,'.self::MAX_MINOR_AMOUNT],
            'items.*.tax_rate_id' => ['required', 'string', 'max:36'],
            'items.*.discount' => ['nullable', new ValidDiscount],

            'document_discount' => ['nullable', new ValidDiscount],
            'notes' => ['nullable', 'string', 'max:5000'],
            'payment_details' => ['nullable', 'string', 'max:2000'],

            'style' => ['required', 'array'],
            'style.template' => ['required', Rule::enum(Template::class)],
            'style.accent_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'style.font' => ['required', Rule::enum(Font::class)],
            'style.date_format' => ['required', Rule::enum(DateFormat::class)],
        ];
    }

    /**
     * Readable names for fields in error messages, e.g. "item 1 quantity" rather than "items.0.quantity".
     *
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        $names = [
            'number' => 'document number',
            'issue_date' => 'issue date',
            'due_date' => 'due date',
            'valid_until' => '"valid until" date',
            'payment_terms_days' => 'payment terms',
            'original_invoice_number' => 'original invoice number',
            'from.logo' => 'logo',
            'from.logo_width' => 'logo size',
            'from.logo_align' => 'logo alignment',
            'from.phone' => 'business phone',
            'from.website' => 'business website',
            'to.contact_name' => 'customer contact name',
            'tax_rates.*.name' => 'tax rate name',
            'tax_rates.*.rate_percent' => 'tax rate',
            'items.*.description' => 'item :position description',
            'items.*.quantity' => 'item :position quantity',
            'items.*.unit_price_minor' => 'item :position unit price',
            'items.*.tax_rate_id' => 'item :position tax rate',
            'items.*.discount' => 'item :position discount',
            'document_discount' => 'document discount',
            'payment_details' => 'payment details',
            'style.template' => 'template',
            'style.accent_color' => 'accent colour',
            'style.font' => 'font',
            'style.date_format' => 'date format',
        ];

        foreach (['from' => 'business', 'to' => 'customer'] as $party => $label) {
            foreach (['company_name' => 'name', 'address_line_1' => 'address line 1', 'address_line_2' => 'address line 2', 'city' => 'city', 'region' => 'region', 'postcode' => 'postcode', 'country' => 'country', 'email' => 'email', 'tax_number' => 'tax number'] as $field => $name) {
                $names["{$party}.{$field}"] = "{$label} {$name}";
            }
        }

        return $names;
    }

    /**
     * Rules shared by the "from" and "to" parties.
     *
     * @return array<string, list<string>>
     */
    private static function partyRules(string $party): array
    {
        return [
            "{$party}.company_name" => ['nullable', 'string', 'max:150'],
            "{$party}.address_line_1" => ['nullable', 'string', 'max:150'],
            "{$party}.address_line_2" => ['nullable', 'string', 'max:150'],
            "{$party}.city" => ['nullable', 'string', 'max:100'],
            "{$party}.region" => ['nullable', 'string', 'max:100'],
            "{$party}.postcode" => ['nullable', 'string', 'max:20'],
            "{$party}.country" => ['nullable', 'string', 'max:100'],
            "{$party}.email" => ['nullable', 'string', 'email', 'max:150'],
            "{$party}.tax_number" => ['nullable', 'string', 'max:50'],
        ];
    }
}
