<?php

namespace App\Livewire;

use App\Documents\Data\DocumentData;
use App\Documents\DocumentCalculator;
use App\Documents\DocumentDefaults;
use App\Documents\DraftSanitiser;
use App\Documents\Enums\DiscountType;
use App\Documents\Enums\DocumentType;
use App\Documents\Support\CurrencyOptions;
use App\Documents\Validation\DocumentRules;
use App\Livewire\Concerns\ManagesLineItems;
use App\Livewire\Concerns\ManagesMoneyInputs;
use App\Livewire\Concerns\ManagesTaxRates;
use Brick\Money\Currency;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The editor: holds the draft in the browser's array shape and renders the live preview.
 *
 * The logo is deliberately not part of this state. It lives in Alpine (see resources/js/editor.js)
 * so a large data URI isn't sent on every round-trip.
 */
class DocumentEditor extends Component
{
    use ManagesLineItems;
    use ManagesMoneyInputs;
    use ManagesTaxRates;

    /** @var array<string, mixed> */
    public array $document = [];

    /** Whether there are changes since the document was created or last exported. */
    public bool $dirty = false;

    /** @var array<string, string> parse errors for money inputs, keyed by input name */
    #[Locked]
    public array $moneyInputErrors = [];

    public function mount(): void
    {
        $type = DocumentType::Invoice;
        $this->document = DocumentDefaults::document($type, CarbonImmutable::today(), $type->defaultNumberPrefix().'0001');
        $this->syncMoneyInputs();
    }

    /**
     * Replace the state with a draft from the browser, repairing anything invalid.
     *
     * @param  array<array-key, mixed>  $data
     */
    public function loadDraft(array $data): void
    {
        $this->document = app(DraftSanitiser::class)->sanitise($data);
        $this->document['from']['logo'] = null;
        $this->moneyInputErrors = [];
        $this->dirty = false;
        $this->syncMoneyInputs();
    }

    /**
     * Start a new document of the current type. Your business details, tax rates, currency,
     * payment details and style carry over; everything specific to the old document is cleared.
     */
    public function newDocument(): void
    {
        $type = DocumentType::tryFrom((string) $this->document['type']) ?? DocumentType::Invoice;
        $fresh = DocumentDefaults::document($type, CarbonImmutable::today(), $type->defaultNumberPrefix().'0001');

        $this->document = array_merge($fresh, array_intersect_key($this->document, array_flip([
            'from', 'tax_rates', 'currency', 'payment_terms_days', 'payment_details', 'style',
        ])));
        $this->document['items'] = [DocumentDefaults::item($this->defaultTaxRateId())];
        $this->applyPaymentTerms();

        $this->moneyInputErrors = [];
        $this->dirty = false;
        $this->syncMoneyInputs();
    }

    public function setType(string $type): void
    {
        $new = DocumentType::tryFrom($type);
        $old = DocumentType::tryFrom((string) $this->document['type']);

        if ($new === null || $new === $old) {
            return;
        }

        $this->document['type'] = $new->value;

        // Until numbering arrives, swap the default prefix so an "INV-" number doesn't end up on a quote.
        if ($old !== null && str_starts_with((string) $this->document['number'], $old->defaultNumberPrefix())) {
            $this->document['number'] = $new->defaultNumberPrefix().substr((string) $this->document['number'], strlen($old->defaultNumberPrefix()));
        }

        $issueDate = $this->issueDate();

        if ($new === DocumentType::Invoice) {
            $this->applyPaymentTerms();
            $this->document['due_date'] ??= $issueDate->addDays(DocumentDefaults::PAYMENT_TERMS_DAYS)->format('Y-m-d');
        }

        if ($new === DocumentType::Quote) {
            $this->document['valid_until'] ??= $issueDate->addDays(DocumentDefaults::QUOTE_VALID_DAYS)->format('Y-m-d');
        }

        $this->dirty = true;
    }

    public function setDocumentDiscountType(string $type): void
    {
        $this->document['document_discount'] = $this->newDiscount(DiscountType::tryFrom($type));
        $this->documentDiscountAmount = '';
        $this->dirty = true;
    }

    public function updated(string $property): void
    {
        if ($property !== 'dirty') {
            $this->dirty = true;
        }
    }

    public function updatedDocument(mixed $value, string $key): void
    {
        match ($key) {
            'payment_terms_days' => $this->setPaymentTerms($value),
            'issue_date' => $this->applyPaymentTerms(),
            'currency' => $this->changeCurrency(),
            'from.logo_width' => $this->document['from']['logo_width'] = (int) $value,
            'from.logo' => $this->document['from']['logo'] = null,
            default => null,
        };
    }

    public function render(): View
    {
        $this->setErrorBag($this->validationErrors());

        $preview = DocumentData::fromArray(app(DraftSanitiser::class)->sanitise($this->document));

        return view('livewire.document-editor', [
            'preview' => $preview,
            'totals' => app(DocumentCalculator::class)->calculate($preview),
            'type' => $preview->type,
            'currencies' => CurrencyOptions::grouped(),
            'currencySymbol' => CurrencyOptions::symbol($preview->currency),
        ]);
    }

    private function setPaymentTerms(mixed $value): void
    {
        $this->document['payment_terms_days'] = $value === '' || $value === null ? null : (int) $value;
        $this->applyPaymentTerms();
    }

    /**
     * Set an invoice's due date from its payment terms. Custom terms (null) leave it alone.
     */
    private function applyPaymentTerms(): void
    {
        $days = $this->document['payment_terms_days'] ?? null;

        if ($this->document['type'] !== DocumentType::Invoice->value || ! is_int($days)) {
            return;
        }

        $this->document['due_date'] = $this->issueDate()->addDays($days)->format('Y-m-d');
    }

    private function changeCurrency(): void
    {
        $code = (string) $this->document['currency'];

        if (! CurrencyOptions::isSupported($code)) {
            return;
        }

        $this->convertMoneyInputsTo(Currency::of($code));
        $this->moneyInputErrors = [];
    }

    private function issueDate(): CarbonImmutable
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', (string) ($this->document['issue_date'] ?? ''));

        return $date ?: CarbonImmutable::today();
    }

    /**
     * Validate the draft with the same rules as the PDF request, keyed by wire:model path.
     */
    private function validationErrors(): MessageBag
    {
        $validator = Validator::make($this->document, DocumentRules::rules(), [], [
            'number' => 'document number',
            'items.*.quantity' => 'quantity',
            'items.*.unit_price_minor' => 'unit price',
            'items.*.description' => 'description',
            'items.*.discount' => 'discount',
            'tax_rates.*.name' => 'tax name',
            'tax_rates.*.rate_percent' => 'rate',
            'style.accent_color' => 'accent colour',
            'from.*' => 'field',
            'to.*' => 'field',
        ]);

        $errors = new MessageBag;

        foreach ($validator->errors()->messages() as $key => $messages) {
            $errors->add("document.{$key}", $messages[0]);
        }

        foreach ($this->moneyInputErrors as $key => $message) {
            $errors->add($key, $message);
        }

        return $errors;
    }
}
