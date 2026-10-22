<x-editor.section id="business" title="Your business">
    <x-form.input label="Company name" model="document.from.company_name" autocomplete="organization" />

    <div>
        <span class="label" id="logo-label">Logo</span>
        <div
            x-show="! logo"
            x-on:dragover.prevent="draggingLogo = true"
            x-on:dragleave.prevent="draggingLogo = false"
            x-on:drop.prevent="dropLogo($event)"
            :class="draggingLogo ? 'border-blue-600 bg-blue-50' : 'border-slate-300'"
            class="rounded-lg border-2 border-dashed p-4 text-center"
        >
            <label class="btn cursor-pointer focus-within:ring-2 focus-within:ring-blue-600 focus-within:ring-offset-2">
                <x-icon name="upload" />
                Upload logo
                <input type="file" class="sr-only" accept="image/png,image/jpeg,image/webp,image/svg+xml" aria-labelledby="logo-label" x-on:change="setLogo($event.target.files[0]); $event.target.value = ''">
            </label>
            <p class="hint">or drag and drop a PNG, JPEG, WebP or SVG. Large images are resized to 800px.</p>
        </div>

        <div x-show="logo" x-cloak class="space-y-4 rounded-lg border border-slate-200 p-4">
            <div class="flex items-center gap-4">
                <div wire:ignore class="flex h-16 w-32 items-center justify-center rounded bg-slate-50">
                    <img x-bind:src="logo" alt="Logo preview" class="max-h-16 max-w-32 object-contain">
                </div>
                <button type="button" class="btn" x-on:click="removeLogo()">
                    <x-icon name="trash" />
                    Remove
                </button>
            </div>

            <div>
                <label for="field-logo-width" class="label">Logo size</label>
                <input id="field-logo-width" type="range" min="40" max="240" step="4" wire:model.live.debounce.150ms="document.from.logo_width" class="w-full accent-blue-700">
            </div>

            <fieldset>
                <legend class="label">Logo position</legend>
                <div class="segmented">
                    @foreach (\App\Documents\Enums\LogoAlign::cases() as $align)
                        <button type="button" wire:click="$set('document.from.logo_align', '{{ $align->value }}')" aria-pressed="{{ ($document['from']['logo_align'] ?? 'left') === $align->value ? 'true' : 'false' }}">
                            {{ ucfirst($align->value) }}
                        </button>
                    @endforeach
                </div>
            </fieldset>
        </div>

        <p x-show="logoError" x-text="logoError" x-cloak class="field-error" role="alert"></p>
    </div>

    @include('livewire.document-editor.sections.address', ['party' => 'from'])

    <div class="grid gap-4 sm:grid-cols-2">
        <x-form.input label="Email" model="document.from.email" type="email" autocomplete="email" />
        <x-form.input label="Phone" model="document.from.phone" type="tel" autocomplete="tel" />
        <x-form.input label="Website" model="document.from.website" autocomplete="url" />
        <x-form.input label="Tax number" model="document.from.tax_number" hint="For example, your VAT number." />
    </div>
</x-editor.section>
