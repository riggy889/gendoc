<div x-data="documentEditor" class="flex min-h-dvh flex-col bg-slate-100 lg:h-dvh">
    @include('livewire.document-editor.toolbar')

    {{-- Mobile: switch between the editor and the preview. --}}
    <div class="sticky top-0 z-20 border-b border-slate-200 bg-white px-4 py-2 lg:hidden">
        <div class="segmented w-full" role="tablist" aria-label="View">
            <button type="button" role="tab" id="tab-edit" aria-controls="panel-edit" class="flex-1" :aria-selected="tab === 'edit'" x-on:click="tab = 'edit'">Edit</button>
            <button type="button" role="tab" id="tab-preview" aria-controls="panel-preview" class="flex-1" :aria-selected="tab === 'preview'" x-on:click="tab = 'preview'">Preview</button>
        </div>
    </div>

    {{-- Hidden until the saved draft has loaded, so the blank starting document doesn't flash up first. --}}
    <main class="flex-1 transition-opacity lg:grid lg:min-h-0 lg:grid-cols-[3fr_2fr]" :class="restored ? '' : 'opacity-0'">
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
