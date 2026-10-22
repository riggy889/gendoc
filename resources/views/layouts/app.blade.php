@props([
    'title' => null,
    'description' => 'Create professional quotes, invoices and credit notes for free. No sign-up: your documents stay in your browser. Customise the design and download a PDF in seconds.',
    // The editor fills the screen on desktop, with the footer as a slim bar beneath it.
    'fullHeight' => false,
])

@php
    $pageTitle = $title ? "{$title} – ".config('app.name') : config('app.name').' – Free Quote, Invoice & Credit Note Generator';
    $umamiUrl = config('services.umami.script_url');
    $umamiId = config('services.umami.website_id');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta name="theme-color" content="#1d4ed8">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('og-image.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ config('app.name') }}: free quotes, invoices and credit notes">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    {{ $head ?? '' }}

    @if ($umamiUrl && $umamiId)
        <script defer src="{{ $umamiUrl }}" data-website-id="{{ $umamiId }}" nonce="{{ Vite::cspNonce() }}"></script>
    @endif
</head>
<body @class(['flex min-h-dvh flex-col bg-slate-100 text-slate-900 antialiased', 'lg:h-dvh' => $fullHeight])>
    {{ $slot }}

    <footer class="border-t border-slate-200 bg-white px-4 py-2 text-sm text-slate-600">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}</p>
            <a href="{{ route('privacy') }}" class="underline-offset-2 hover:text-slate-900 hover:underline focus-visible:rounded focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:outline-none">Privacy</a>

            @if (config('services.tips.url'))
                <a
                    href="{{ config('services.tips.url') }}"
                    target="_blank"
                    rel="noopener"
                    class="ml-auto rounded-full border border-slate-200 px-3 py-1 text-xs hover:border-slate-300 hover:text-slate-900 focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:outline-none"
                >
                    <span aria-hidden="true">☕</span> Support this free tool
                </a>
            @endif
        </div>
    </footer>

    @livewireScriptConfig
</body>
</html>
