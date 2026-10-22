@props(['label', 'model', 'error' => null, 'hint' => null])

@php
    $id = 'field-'.\Illuminate\Support\Str::slug($model);
    $errorKey = $error ?? $model;
@endphp

<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="label">{{ $label }}</label>
    <select
        id="{{ $id }}"
        wire:model.live="{{ $model }}"
        {{ $attributes->except('class')->class(['input']) }}
        @if ($errors->has($errorKey)) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
    >
        {{ $slot }}
    </select>
    @if ($hint)
        <p class="hint">{{ $hint }}</p>
    @endif
    @error($errorKey)
        <p id="{{ $id }}-error" class="field-error">{{ $message }}</p>
    @enderror
</div>
