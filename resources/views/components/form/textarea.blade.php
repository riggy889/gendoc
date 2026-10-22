@props(['label', 'model', 'rows' => 3, 'error' => null, 'hint' => null])

@php
    $id = 'field-'.\Illuminate\Support\Str::slug($model);
    $errorKey = $error ?? $model;
    $describedBy = trim(($errors->has($errorKey) ? "{$id}-error " : '').($hint ? "{$id}-hint" : ''));
@endphp

<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="label">{{ $label }}</label>
    <textarea
        id="{{ $id }}"
        rows="{{ $rows }}"
        wire:model.live.debounce.300ms="{{ $model }}"
        {{ $attributes->except('class')->class(['input resize-y']) }}
        @if ($errors->has($errorKey)) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
    ></textarea>
    @if ($hint)
        <p id="{{ $id }}-hint" class="hint">{{ $hint }}</p>
    @endif
    @error($errorKey)
        <p id="{{ $id }}-error" class="field-error">{{ $message }}</p>
    @enderror
</div>
