<header class="border-b border-slate-200 bg-white">
    <div class="flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-2.5">
        <p class="text-lg font-bold tracking-tight text-slate-900 sm:mr-2">Gen<span class="text-blue-700">Doc</span></p>

        <div class="segmented" role="group" aria-label="Document type">
            @foreach (\App\Documents\Enums\DocumentType::cases() as $option)
                <button type="button" wire:click="setType('{{ $option->value }}')" aria-pressed="{{ $type === $option ? 'true' : 'false' }}">
                    {{ $option->title() }}
                </button>
            @endforeach
        </div>

        @if ($type === \App\Documents\Enums\DocumentType::Quote)
            <button type="button" class="btn" wire:click="convertToInvoice">
                <x-icon name="arrow-right" />
                Convert to invoice
            </button>
        @elseif ($type === \App\Documents\Enums\DocumentType::Invoice)
            <button type="button" class="btn" wire:click="createCreditNote">
                <x-icon name="arrow-right" />
                Create credit note
            </button>
        @endif

        <div class="ml-auto flex flex-wrap items-center gap-2">
            <button
                type="button"
                class="btn"
                x-on:click="if (! $wire.dirty || confirm('Start a new document? Your current changes will be lost.')) { $wire.newDocument() }"
            >
                <x-icon name="file-plus" />
                <span class="max-sm:sr-only">New</span>
            </button>

            <input
                type="file"
                accept="application/json,.json"
                class="sr-only"
                tabindex="-1"
                aria-hidden="true"
                x-ref="importFile"
                x-on:change="importJson($event.target.files[0], '{{ route('documents.import') }}'); $event.target.value = ''"
            >
            <button type="button" class="btn" x-on:click="$refs.importFile.click()">
                <x-icon name="file-in" />
                <span class="max-sm:sr-only">Import</span>
            </button>
            <button type="button" class="btn" x-on:click="exportJson()">
                <x-icon name="file-out" />
                <span class="max-sm:sr-only">Export</span>
            </button>

            <button
                type="button"
                class="btn btn-primary"
                x-on:click="downloadPdf('{{ route('documents.pdf') }}')"
                x-bind:disabled="downloading"
                x-bind:aria-busy="downloading"
            >
                <x-icon name="download" x-show="! downloading" />
                <x-icon name="loader" class="animate-spin" x-show="downloading" x-cloak />
                Download PDF
            </button>
        </div>
    </div>
</header>
