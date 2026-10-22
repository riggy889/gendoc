<?php

namespace App\Http\Controllers;

use App\Documents\Data\DocumentData;
use App\Http\Requests\ImportDocumentRequest;
use Illuminate\Http\JsonResponse;

/**
 * Validates an imported JSON document and returns it cleaned up (trimmed text, sanitised logo).
 *
 * This is a plain endpoint rather than a Livewire action because a document with a logo can be
 * larger than Livewire's request size limit. Nothing is stored.
 */
class DocumentImportController extends Controller
{
    public function __invoke(ImportDocumentRequest $request): JsonResponse
    {
        return response()->json([
            'document' => DocumentData::fromArray($request->validated())->toArray(),
        ]);
    }
}
