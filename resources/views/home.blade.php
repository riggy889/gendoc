<x-layouts::app :full-height="true">
    <x-slot:head>
        {{-- Runs before first paint: if a draft is saved, hide the editor until it has been restored. --}}
        <script nonce="{{ Vite::cspNonce() }}">
            try { if (localStorage.getItem('gendoc.draft')) document.documentElement.classList.add('has-draft'); } catch {}
        </script>
    </x-slot:head>

    <livewire:document-editor />
</x-layouts::app>
