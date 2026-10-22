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

        <div class="ml-auto flex items-center gap-2">
            <button
                type="button"
                class="btn"
                x-on:click="if (! $wire.dirty || confirm('Start a new document? Your current changes will be lost.')) { $wire.newDocument() }"
            >
                <x-icon name="file-plus" />
                <span class="max-sm:sr-only">New</span>
            </button>
        </div>
    </div>
</header>
