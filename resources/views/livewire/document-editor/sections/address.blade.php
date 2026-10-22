<div class="grid gap-4 sm:grid-cols-2">
    <x-form.input label="Address line 1" model="document.{{ $party }}.address_line_1" class="sm:col-span-2" />
    <x-form.input label="Address line 2" model="document.{{ $party }}.address_line_2" class="sm:col-span-2" />
    <x-form.input label="Town or city" model="document.{{ $party }}.city" />
    <x-form.input label="County, state or region" model="document.{{ $party }}.region" />
    <x-form.input label="Postcode" model="document.{{ $party }}.postcode" />
    <x-form.input label="Country" model="document.{{ $party }}.country" />
</div>
