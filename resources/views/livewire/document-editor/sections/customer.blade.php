<x-editor.section id="customer" title="Customer">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-form.input label="Company name" model="document.to.company_name" />
        <x-form.input label="Contact name" model="document.to.contact_name" />
    </div>

    @include('livewire.document-editor.sections.address', ['party' => 'to'])

    <div class="grid gap-4 sm:grid-cols-2">
        <x-form.input label="Email" model="document.to.email" type="email" />
        <x-form.input label="Tax number" model="document.to.tax_number" />
    </div>
</x-editor.section>
