@props([
    'label',
    'model',
    'type' => 'text',
    'live' => true,
    'error' => null,
    'hint' => null,
    'prefix' => null,
])

@php
    $id = 'field-'.\Illuminate\Support\Str::slug($model);
    $errorKey = $error ?? $model;
    $binding = $live ? 'wire:model.live.debounce.300ms' : 'wire:model.live';
    $describedBy = trim(($errors->has($errorKey) ? "{$id}-error " : '').($hint ? "{$id}-hint" : ''));
@endphp

<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="label">{{ $label }}</label>
    <div @class(['relative' => $prefix])>
        @if ($prefix)
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-500" aria-hidden="true">{{ $prefix }}</span>
        @endif
        <input
            id="{{ $id }}"
            type="{{ $type }}"
            {{ $attributes->except('class')->merge([$binding => $model])->class(['input', 'pl-12' => $prefix && mb_strlen($prefix) > 2, 'pl-7' => $prefix && mb_strlen($prefix) <= 2]) }}
            @if ($errors->has($errorKey)) aria-invalid="true" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        >
    </div>
    @if ($hint)
        <p id="{{ $id }}-hint" class="hint">{{ $hint }}</p>
    @endif
    @error($errorKey)
        <p id="{{ $id }}-error" class="field-error">{{ $message }}</p>
    @enderror
</div>
