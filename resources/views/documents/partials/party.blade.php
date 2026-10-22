{{-- @var string $heading, array<int, string|null> $lines --}}
@php($lines = array_values(array_filter($lines, fn ($line) => $line !== null && $line !== '')))

@if ($lines !== [])
    <div class="doc-party">
        <h2 class="doc-label">{{ $heading }}</h2>
        @foreach ($lines as $line)
            <p @class(['doc-party__name' => $loop->first && $emphasiseFirst])>{{ $line }}</p>
        @endforeach
    </div>
@endif
