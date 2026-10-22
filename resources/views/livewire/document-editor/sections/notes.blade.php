<x-editor.section id="notes" title="Notes & payment details">
    <x-form.textarea label="Notes" model="document.notes" rows="3" placeholder="For example, thank you for your business." />
    <x-form.textarea label="Payment details" model="document.payment_details" rows="5"
        placeholder="Bank name&#10;Account name&#10;Sort code&#10;Account number&#10;IBAN&#10;Payment reference"
        hint="Shown at the bottom of the document." />
</x-editor.section>
