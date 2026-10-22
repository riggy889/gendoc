<?php

namespace App\Http\Requests;

use App\Documents\Validation\DocumentRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A full document payload, validated with the rules shared by PDF generation and JSON import.
 */
abstract class DocumentPayloadRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return DocumentRules::rules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return DocumentRules::attributes();
    }
}
