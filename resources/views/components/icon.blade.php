@props(['name'])

<svg {{ $attributes->merge(['class' => 'size-4 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('chevron-down') <path d="m6 9 6 6 6-6"/> @break
        @case('arrow-up') <path d="m18 15-6-6-6 6"/> @break
        @case('arrow-down') <path d="m6 9 6 6 6-6"/> @break
        @case('grip') <circle cx="9" cy="6" r="1"/><circle cx="15" cy="6" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="9" cy="18" r="1"/><circle cx="15" cy="18" r="1"/> @break
        @case('copy') <rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/> @break
        @case('trash') <path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/> @break
        @case('plus') <path d="M12 5v14M5 12h14"/> @break
        @case('x') <path d="M18 6 6 18M6 6l12 12"/> @break
        @case('upload') <path d="M12 16V4m0 0-4 4m4-4 4 4"/><path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/> @break
        @case('download') <path d="M12 4v12m0 0-4-4m4 4 4-4"/><path d="M4 18v2h16v-2"/> @break
        @case('loader') <path d="M21 12a9 9 0 1 1-6.22-8.56"/> @break
        @case('file-plus') <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M12 12v6M9 15h6"/> @break
    @endswitch
</svg>
