<div x-data="documentEditor" class="flex flex-1 flex-col bg-slate-100 lg:min-h-0">
    @include('livewire.document-editor.toolbar')

    <section class="border-b border-slate-200 bg-white px-4 pb-3" aria-labelledby="intro-heading">
        <h1 id="intro-heading" class="text-base font-semibold text-slate-900 sm:text-lg">Free quote, invoice and credit note generator</h1>
        <p class="mt-0.5 max-w-3xl text-sm text-slate-600">
            Fill in your details, choose a design and download a professional PDF. No sign-up, no watermarks, and your documents never leave your browser.
        </p>
        <noscript><p class="mt-2 text-sm font-medium text-red-700">GenDoc needs JavaScript to be turned on.</p></noscript>
    </section>

    {{-- Mobile: switch between the editor and the preview. --}}
    <div class="sticky top-0 z-20 border-b border-slate-200 bg-white px-4 py-2 lg:hidden">
        <div class="segmented w-full" role="tablist" aria-label="View">
            <button type="button" role="tab" id="tab-edit" aria-controls="panel-edit" class="flex-1" :aria-selected="tab === 'edit'" x-on:click="tab = 'edit'">Edit</button>
            <button type="button" role="tab" id="tab-preview" aria-controls="panel-preview" class="flex-1" :aria-selected="tab === 'preview'" x-on:click="tab = 'preview'">Preview</button>
        </div>
    </div>

    {{-- When there's a saved draft, hidden until it has loaded so the blank starting document doesn't flash up (see home.blade.php). --}}
    <main class="editor-main flex-1 transition-opacity lg:grid lg:min-h-0 lg:grid-cols-[3fr_2fr]" :class="restored && 'is-restored'">
        <div id="panel-preview" role="tabpanel" aria-labelledby="tab-preview" class="hidden min-h-0 lg:block" :class="tab === 'preview' && 'max-lg:block!'">
            @include('livewire.document-editor.preview')
        </div>

        <div id="panel-edit" role="tabpanel" aria-labelledby="tab-edit" class="min-h-0 border-slate-200 bg-white lg:overflow-y-auto lg:border-l" :class="tab === 'preview' && 'max-lg:hidden'">
            <h2 class="sr-only">Customise your document</h2>
            @include('livewire.document-editor.sections.document')
            @include('livewire.document-editor.sections.business')
            @include('livewire.document-editor.sections.customer')
            @include('livewire.document-editor.sections.items')
            @include('livewire.document-editor.sections.tax')
            @include('livewire.document-editor.sections.notes')
            @include('livewire.document-editor.sections.style')
        </div>
    </main>

    @include('livewire.document-editor.toasts')
</div>
