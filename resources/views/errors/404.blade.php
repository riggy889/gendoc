<x-layouts::app title="Page not found">
    <main class="mx-auto flex w-full max-w-xl flex-1 flex-col items-center justify-center px-4 py-16 text-center">
        <p class="text-sm font-semibold text-blue-700">404</p>
        <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">Page not found</h1>
        <p class="mt-2 text-slate-600">Sorry, we couldn't find that page. Your saved documents are safe.</p>
        <a href="{{ route('home') }}" class="btn btn-primary mt-6">Back to the editor</a>
    </main>
</x-layouts::app>
