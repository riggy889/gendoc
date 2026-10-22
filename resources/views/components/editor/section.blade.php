{{-- $open: render expanded before Alpine starts. Only the default section (see editor.js) should set it, to avoid a layout shift on load. --}}
@props(['id', 'title', 'open' => false])

<section class="border-b border-slate-200">
    <h2>
        <button
            type="button"
            id="section-{{ $id }}-button"
            aria-controls="section-{{ $id }}"
            :aria-expanded="openSection === '{{ $id }}'"
            x-on:click="toggleSection('{{ $id }}')"
            class="flex w-full items-center justify-between px-5 py-4 text-left text-base font-semibold text-slate-900 hover:bg-slate-50 focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:outline-none focus-visible:ring-inset"
        >
            {{ $title }}
            <x-icon name="chevron-down" class="size-5 text-slate-500 transition-transform" x-bind:class="openSection === '{{ $id }}' && 'rotate-180'" />
        </button>
    </h2>
    <div
        id="section-{{ $id }}"
        role="region"
        aria-labelledby="section-{{ $id }}-button"
        x-show="openSection === '{{ $id }}'"
        @unless ($open) x-cloak @endunless
        class="space-y-4 px-5 pt-1 pb-6"
    >
        {{ $slot }}
    </div>
</section>
